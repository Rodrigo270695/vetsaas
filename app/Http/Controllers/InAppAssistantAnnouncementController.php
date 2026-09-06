<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\InAppAssistantAnnouncementRequest;
use App\Models\InAppAssistantAnnouncement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de novedades in-app para clínicas (Plataforma → Configuración).
 * Hasta {@see InAppAssistantAnnouncement::MAX_LIVE} pueden estar activas a la vez.
 */
final class InAppAssistantAnnouncementController extends Controller
{
    public function store(InAppAssistantAnnouncementRequest $request): RedirectResponse
    {
        $data = $this->normalize($request->validated());
        $publishNow = (bool) ($request->validated()['publish_now'] ?? true);

        DB::transaction(function () use ($data, $publishNow): void {
            if ($publishNow) {
                $this->assertCanActivate();
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
            if ($publishNow && ! $novedad->is_active) {
                $this->assertCanActivate();
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
            if (! $novedad->is_active) {
                $this->assertCanActivate();
            }

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
            if (! $novedad->is_active) {
                $this->assertCanActivate();
            }

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

    public function deactivate(InAppAssistantAnnouncement $novedad): RedirectResponse
    {
        $novedad->is_active = false;
        $novedad->save();

        return back()->with('success', 'Novedad desactivada.');
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

    private function assertCanActivate(): void
    {
        if (InAppAssistantAnnouncement::liveCount() >= InAppAssistantAnnouncement::MAX_LIVE) {
            throw ValidationException::withMessages([
                'publish_now' => 'Ya hay '.InAppAssistantAnnouncement::MAX_LIVE.' novedades activas. Desactiva una para publicar otra.',
            ]);
        }
    }
}
