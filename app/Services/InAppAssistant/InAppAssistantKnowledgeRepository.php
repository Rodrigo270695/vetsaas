<?php

declare(strict_types=1);

namespace App\Services\InAppAssistant;

use App\Models\InAppAssistantKnowledge;
use App\Models\User;
use Illuminate\Support\Str;

final class InAppAssistantKnowledgeRepository
{
    /** @var array<string, string> */
    private const TOUR_PERMISSIONS = [
        'citas' => 'citas.view',
        'pacientes' => 'pacientes.view',
        'historias-clinicas' => 'historias-clinicas.view',
    ];

    public function __construct(
        private readonly int $maxEntries = 6,
        private readonly int $maxCharacters = 10000,
    ) {}

    /**
     * @param  array{url?: string, component?: string}|null  $pageContext
     * @return array{
     *     context: string,
     *     actions: list<array{type: 'navigate', url: string, label: string}|array{type: 'start_tour', tour_id: string, label: string}>,
     *     entries: list<array{slug: string, title: string, score: int}>
     * }
     */
    public function search(
        string $message,
        string $scope,
        ?array $pageContext,
        ?User $user,
    ): array {
        $scope = in_array($scope, [
            InAppAssistantKnowledge::SCOPE_CLINIC,
            InAppAssistantKnowledge::SCOPE_PLATFORM,
        ], true) ? $scope : InAppAssistantKnowledge::SCOPE_CLINIC;

        $isSuperadmin = $user?->isPlatformSuperadmin() ?? false;
        $permissions = $isSuperadmin ? [] : $this->permissionsFor($user);
        $roles = $isSuperadmin ? [] : $this->rolesFor($user);

        $ranked = [];
        foreach (InAppAssistantKnowledge::cachedActiveRows() as $row) {
            if (! $this->matchesScope($row, $scope)
                || ! $this->isAuthorized($row, $permissions, $roles, $isSuperadmin)) {
                continue;
            }

            $score = $this->score($row, $message, $pageContext);
            if ($score <= 0) {
                continue;
            }

            $row['_score'] = $score;
            $ranked[] = $row;
        }

        usort($ranked, static function (array $left, array $right): int {
            return [
                (int) $right['_score'],
                (int) ($right['priority'] ?? 0),
                -((int) ($right['sort_order'] ?? 0)),
            ] <=> [
                (int) $left['_score'],
                (int) ($left['priority'] ?? 0),
                -((int) ($left['sort_order'] ?? 0)),
            ];
        });

        $selected = array_slice($ranked, 0, max(1, $this->maxEntries));
        $context = '';
        $entries = [];
        $actions = [];
        $seenActions = [];

        foreach ($selected as $row) {
            $block = '## '.trim((string) $row['title'])."\n"
                .trim((string) $row['content'])."\n\n";
            $remaining = max(0, $this->maxCharacters - mb_strlen($context));
            if ($remaining === 0) {
                break;
            }

            if (mb_strlen($block) > $remaining) {
                $block = rtrim(mb_substr($block, 0, $remaining));
            }
            $context .= $block;
            $entries[] = [
                'slug' => (string) $row['slug'],
                'title' => (string) $row['title'],
                'score' => (int) $row['_score'],
            ];

            foreach ($this->safeActions($row, $permissions, $roles, $isSuperadmin, $scope, $user) as $action) {
                $key = $action['type'].'|'.($action['url'] ?? $action['tour_id'] ?? '');
                if (! isset($seenActions[$key])) {
                    $seenActions[$key] = true;
                    $actions[] = $action;
                }
            }
        }

        return [
            'context' => trim($context),
            'actions' => $actions,
            'entries' => $entries,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function score(array $row, string $message, ?array $pageContext): int
    {
        $query = $this->normalize($message);
        $tokens = array_values(array_unique(array_filter(
            preg_split('/\s+/u', $query) ?: [],
            static fn (string $token): bool => mb_strlen($token) >= 3,
        )));
        $title = $this->normalize((string) ($row['title'] ?? ''));
        $content = $this->normalize((string) ($row['content'] ?? ''));
        $score = 0;

        if ($query !== '' && $title !== '') {
            if ($query === $title) {
                $score += 140;
            } elseif (str_contains($title, $query) || str_contains($query, $title)) {
                $score += 90;
            }
        }

        foreach ($tokens as $token) {
            if (str_contains($title, $token)) {
                $score += 24;
            }
            if (str_contains($content, $token)) {
                $score += 3;
            }
        }

        foreach ($this->stringList($row['keywords'] ?? null) as $keyword) {
            $keyword = $this->normalize($keyword);
            if ($keyword === '' || $query === '') {
                continue;
            }
            if ($query === $keyword) {
                $score += 100;
            } elseif (str_contains($query, $keyword) || str_contains($keyword, $query)) {
                $score += 55;
            } else {
                foreach ($tokens as $token) {
                    if (str_contains($keyword, $token)) {
                        $score += 15;
                    }
                }
            }
        }

        $url = trim((string) ($pageContext['url'] ?? ''));
        $component = trim((string) ($pageContext['component'] ?? ''));
        if ($url !== '' && $this->matchesAnyPattern($url, $this->stringList($row['url_patterns'] ?? null))) {
            $score += 85;
        }
        if ($component !== '' && $this->matchesAnyPattern($component, $this->stringList($row['component_patterns'] ?? null))) {
            $score += 75;
        }

        if ($score > 0) {
            $score += min(30, max(0, (int) ($row['priority'] ?? 0)));
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $permissions
     * @param  array<string, true>  $roles
     */
    private function isAuthorized(array $row, array $permissions, array $roles, bool $isSuperadmin): bool
    {
        if ($isSuperadmin) {
            return true;
        }

        $allowedRoles = $this->stringList($row['allowed_roles'] ?? null);
        if ($allowedRoles !== [] && ! $this->hasAny($allowedRoles, $roles)) {
            return false;
        }

        $required = $this->stringList($row['required_permissions'] ?? null);
        if ($required === []) {
            return true;
        }

        return ($row['permission_mode'] ?? InAppAssistantKnowledge::PERMISSION_ANY)
            === InAppAssistantKnowledge::PERMISSION_ALL
            ? $this->hasAll($required, $permissions)
            : $this->hasAny($required, $permissions);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $permissions
     * @param  array<string, true>  $roles
     * @return list<array{type: 'navigate', url: string, label: string}|array{type: 'start_tour', tour_id: string, label: string}>
     */
    private function safeActions(
        array $row,
        array $permissions,
        array $roles,
        bool $isSuperadmin,
        string $scope,
        ?User $user,
    ): array {
        $result = [];
        $actions = $row['actions'] ?? null;
        if (! is_array($actions)) {
            return [];
        }

        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $type = $action['type'] ?? null;
            $label = trim((string) ($action['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            if ($type === 'navigate') {
                $url = trim((string) ($action['url'] ?? ''));
                if (! preg_match('#^/(?!/)[A-Za-z0-9/_{}.-]*(?:\?[A-Za-z0-9_=&%{}.-]*)?$#', $url)
                    || ! InAppAssistantNavigation::allowsKnowledgeUrl($url, $scope, $user)
                    || ! $this->isAuthorized([
                        'required_permissions' => $action['required_permissions'] ?? [],
                        'permission_mode' => $action['permission_mode'] ?? InAppAssistantKnowledge::PERMISSION_ANY,
                        'allowed_roles' => $action['allowed_roles'] ?? [],
                    ], $permissions, $roles, $isSuperadmin)) {
                    continue;
                }

                $result[] = ['type' => 'navigate', 'url' => $url, 'label' => $label];

                continue;
            }

            if ($type !== 'start_tour') {
                continue;
            }

            $tourId = trim((string) ($action['tour_id'] ?? ''));
            $tourPermission = self::TOUR_PERMISSIONS[$tourId] ?? null;
            if ($tourPermission === null
                || ! $this->isAuthorized([
                    'required_permissions' => array_values(array_unique([
                        $tourPermission,
                        ...$this->stringList($action['required_permissions'] ?? null),
                    ])),
                    'permission_mode' => InAppAssistantKnowledge::PERMISSION_ALL,
                    'allowed_roles' => $action['allowed_roles'] ?? [],
                ], $permissions, $roles, $isSuperadmin)) {
                continue;
            }

            $result[] = ['type' => 'start_tour', 'tour_id' => $tourId, 'label' => $label];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesScope(array $row, string $scope): bool
    {
        return in_array((string) ($row['scope'] ?? ''), [$scope, InAppAssistantKnowledge::SCOPE_BOTH], true);
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchesAnyPattern(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).'$#iu';
            if (preg_match($regex, $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function permissionsFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_fill_keys($user->getAllPermissions()->pluck('name')->map('strval')->all(), true);
    }

    /**
     * @return array<string, true>
     */
    private function rolesFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_fill_keys($user->getRoleNames()->map('strval')->all(), true);
    }

    /**
     * @param  list<string>  $required
     * @param  array<string, true>  $granted
     */
    private function hasAny(array $required, array $granted): bool
    {
        foreach ($required as $name) {
            if (isset($granted[$name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $required
     * @param  array<string, true>  $granted
     */
    private function hasAll(array $required, array $granted): bool
    {
        foreach ($required as $name) {
            if (! isset($granted[$name])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_string($item) ? trim($item) : '', $value),
            static fn (string $item): bool => $item !== '',
        ));
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', Str::ascii($value)) ?? $value));
    }
}
