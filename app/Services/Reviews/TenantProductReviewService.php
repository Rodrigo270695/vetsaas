<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantProductReview;
use App\Models\User;
use App\Services\Marketing\VetSaaSPublicMarketingService;
use App\Support\Clinic\ClinicBrandingUrls;
use App\Support\Database\PublicSchema;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class TenantProductReviewService
{
    public const DISMISS_COOLDOWN_DAYS = 14;
    /** @var array<string, string> */
    public const ROLE_LABELS = [
        'admin_clinica' => 'Administración',
        'veterinario' => 'Médico veterinario',
        'asistente_vet' => 'Asistente veterinario',
        'recepcionista' => 'Recepcionista',
        'groomer' => 'Groomer',
    ];

    public function promptPayload(User $user, Tenant $tenant): ?array
    {
        if (! $this->shouldPrompt($user, $tenant)) {
            return null;
        }

        $clinic = $this->clinicDisplayName($tenant);
        $role = $this->roleLabelFor($user);

        return [
            'clinic_name' => $clinic,
            'role_label' => $role,
            'author_name' => $this->authorDisplayName($user),
            'role_line' => $this->roleLine($role, $clinic),
        ];
    }

    public function shouldPrompt(User $user, Tenant $tenant): bool
    {
        if (! PublicSchema::hasTable('tenant_product_reviews')) {
            return false;
        }

        if ($user->tenant_id === null || (string) $user->tenant_id !== (string) $tenant->id) {
            return false;
        }

        if ($tenant->slug === 'demo') {
            return false;
        }

        $row = TenantProductReview::query()->where('user_id', $user->id)->first();
        if ($row !== null && $row->isSubmitted()) {
            return false;
        }

        $today = $this->todayForTenant($tenant);
        if ($row?->prompt_dismissed_on !== null
            && $row->prompt_dismissed_on->copy()->addDays(self::DISMISS_COOLDOWN_DAYS)->gt($today)) {
            return false;
        }

        return true;
    }

    public function dismiss(User $user, Tenant $tenant): void
    {
        if ($user->tenant_id === null || (string) $user->tenant_id !== (string) $tenant->id) {
            return;
        }

        if ($tenant->slug === 'demo') {
            return;
        }

        $row = TenantProductReview::query()->firstOrNew(['user_id' => $user->id]);
        if ($row->isSubmitted()) {
            return;
        }

        $row->tenant_id = $tenant->id;
        $row->prompt_dismissed_on = $this->todayForTenant($tenant);
        $row->save();
    }

    /**
     * @param  array{rating: int, comment: string}  $data
     */
    public function submit(User $user, Tenant $tenant, array $data): TenantProductReview
    {
        $row = TenantProductReview::query()->firstOrNew(['user_id' => $user->id]);
        if ($row->exists && $row->isSubmitted()) {
            return $row;
        }

        $clinic = $this->clinicDisplayName($tenant);
        $role = $this->roleLabelFor($user);

        $row->fill([
            'tenant_id' => $tenant->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'],
            'author_name' => $this->authorDisplayName($user),
            'role_label' => $role,
            'clinic_name' => $clinic,
            'submitted_at' => now(),
            'prompt_dismissed_on' => null,
            'published' => true,
        ]);
        $row->save();

        app(VetSaaSPublicMarketingService::class)->forgetCache();

        return $row;
    }

    /**
     * @return list<array{
     *     author_name: string,
     *     role_label: string,
     *     clinic_name: string,
     *     role_line: string,
     *     rating: int,
     *     comment: string,
     *     submitted_at: string|null,
     *     logo_url: string|null
     * }>
     */
    public function publicReviews(int $limit = 18): array
    {
        if (! PublicSchema::hasTable('tenant_product_reviews')) {
            return [];
        }

        $manager = app(TenantManager::class);
        $logoByTenant = [];

        return TenantProductReview::query()
            ->with('tenant:id,slug,nombre_comercial,razon_social')
            ->whereNotNull('submitted_at')
            ->where('published', true)
            ->where('rating', '>=', 1)
            ->orderByDesc('submitted_at')
            ->limit($limit)
            ->get()
            ->map(function (TenantProductReview $row) use ($manager, &$logoByTenant): array {
                $role = (string) $row->role_label;
                $clinic = (string) $row->clinic_name;
                $tenantId = (string) $row->tenant_id;

                if (! array_key_exists($tenantId, $logoByTenant)) {
                    $logoByTenant[$tenantId] = $this->clinicLogoUrl($manager, $row->tenant);
                }

                return [
                    'author_name' => (string) $row->author_name,
                    'role_label' => $role,
                    'clinic_name' => $clinic,
                    'role_line' => $this->roleLine($role, $clinic),
                    'rating' => (int) $row->rating,
                    'comment' => (string) $row->comment,
                    'submitted_at' => $row->submitted_at?->toIso8601String(),
                    'logo_url' => $logoByTenant[$tenantId],
                ];
            })
            ->all();
    }

    private function clinicLogoUrl(TenantManager $manager, ?Tenant $tenant): ?string
    {
        if ($tenant === null || ! filled($tenant->slug)) {
            return null;
        }

        $branding = ClinicBrandingUrls::resolveForTenant($manager, $tenant);
        if (! $branding['has_custom_logo']) {
            return null;
        }

        $url = trim($branding['logo_url']);

        return $url !== '' ? $url : null;
    }

    public function clinicDisplayName(Tenant $tenant): string
    {
        $name = trim((string) ($tenant->nombre_comercial ?: ''));
        if ($name === '') {
            $name = trim((string) $tenant->razon_social);
        }

        return $name !== '' ? $name : 'su clínica';
    }

    public function roleLabelFor(User $user): string
    {
        $names = $user->getRoleNames()->map(fn ($n) => (string) $n)->all();

        foreach (Role::BASE_CLINIC_ROLES as $key) {
            if (in_array($key, $names, true)) {
                return self::ROLE_LABELS[$key] ?? $this->humanizeRole($key);
            }
        }

        $first = $names[0] ?? '';
        if ($first !== '') {
            return $this->humanizeRole($first);
        }

        return 'Equipo clínico';
    }

    public function authorDisplayName(User $user): string
    {
        $name = trim((string) $user->name);

        return $name !== '' ? $name : 'Profesional de la clínica';
    }

    public function roleLine(string $roleLabel, string $clinicName): string
    {
        $role = trim($roleLabel);
        $clinic = trim($clinicName);
        if ($role === '') {
            $role = 'Equipo clínico';
        }
        if ($clinic === '') {
            return $role;
        }

        return $role.' de '.$clinic;
    }

    public function sanitizeComment(string $comment): string
    {
        $text = strip_tags($comment);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function todayForTenant(Tenant $tenant): Carbon
    {
        $tz = trim((string) ($tenant->timezone ?: 'America/Lima'));
        if ($tz === '') {
            $tz = 'America/Lima';
        }

        return Carbon::now($tz)->startOfDay();
    }

    private function humanizeRole(string $name): string
    {
        $label = str_replace(['_', '-'], ' ', $name);

        return Str::title($label);
    }
}
