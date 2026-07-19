<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\InAppAssistantAnnouncementRequest;
use App\Models\InAppAssistantAnnouncement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de novedades del asistente in-app (Plataforma → Configuración).
 */
final class InAppAssistantAnnouncementController extends Controller
{
    public function store(InAppAssistantAnnouncementRequest $request): RedirectResponse
    {
        $data = $this->normalize($request->validated());
        $publishNow = (bool) ($request->validated()['publish_now'] ?? true);

        DB::transaction(function () use ($data, $publishNow): void {
            if ($publishNow) {
                $this->deactivateAll();
            }

            InAppAssistantAnnouncement::query()->create([
                ...$data,
                'is_active' => $publishNow,
                'version' => 1,
                'published_at' => $publishNow ? now() : null,
                'created_by_id' => Auth::id(),
            ]);
        });

        return back()->with(
            'success',
            $publishNow
                ? 'Novedad creada y publicada para las clínicas.'
                : 'Novedad guardada como borrador.',
        );
    }

    public function update(
        InAppAssistantAnnouncementRequest $request,
        InAppAssistantAnnouncement $novedad,
    ): RedirectResponse {
        $data = $this->normalize($request->validated());
        $publishNow = (bool) ($request->validated()['publish_now'] ?? $novedad->is_active);

        DB::transaction(function () use ($novedad, $data, $publishNow): void {
            if ($publishNow) {
                $this->deactivateAllExcept($novedad->id);
            }

            $novedad->fill($data);
            $novedad->is_active = $publishNow;

            if ($publishNow && $novedad->published_at === null) {
                $novedad->published_at = now();
            }

            $novedad->save();
        });

        return back()->with('success', 'Novedad actualizada correctamente.');
    }

    public function republish(InAppAssistantAnnouncement $novedad): RedirectResponse
    {
        DB::transaction(function () use ($novedad): void {
            $this->deactivateAllExcept($novedad->id);
            $novedad->is_active = true;
            $novedad->version = ((int) $novedad->version) + 1;
            $novedad->published_at = now();
            $novedad->save();
        });

        return back()->with('success', 'Novedad republicada. Las clínicas la verán de nuevo.');
    }

    public function activate(InAppAssistantAnnouncement $novedad): RedirectResponse
    {
        DB::transaction(function () use ($novedad): void {
            $this->deactivateAllExcept($novedad->id);
            $novedad->is_active = true;
            if ($novedad->published_at === null) {
                $novedad->published_at = now();
            }
            if ((int) $novedad->version < 1) {
                $novedad->version = 1;
            }
            $novedad->save();
        });

        return back()->with('success', 'Novedad activada para las clínicas.');
    }

    public function destroy(InAppAssistantAnnouncement $novedad): RedirectResponse
    {
        $novedad->delete();

        return back()->with('success', 'Novedad eliminada.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{title: string, body: string, features: list<string>|null}
     */
    private function normalize(array $validated): array
    {
        $features = is_array($validated['features'] ?? null)
            ? array_values(array_filter(
                array_map(
                    static fn ($item) => is_string($item) ? trim($item) : '',
                    $validated['features'],
                ),
                static fn (string $item) => $item !== '',
            ))
            : [];

        return [
            'title' => (string) $validated['title'],
            'body' => (string) $validated['body'],
            'features' => $features !== [] ? $features : null,
        ];
    }

    private function deactivateAll(): void
    {
        InAppAssistantAnnouncement::query()
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    private function deactivateAllExcept(string $id): void
    {
        InAppAssistantAnnouncement::query()
            ->where('is_active', true)
            ->where('id', '!=', $id)
            ->update(['is_active' => false]);
    }
}
