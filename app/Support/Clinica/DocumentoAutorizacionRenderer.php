<?php

declare(strict_types=1);

namespace App\Support\Clinica;

use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\DocumentoAutorizacionPlantilla;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Support\Geo\MojibakeFixer;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

final class DocumentoAutorizacionRenderer
{
    public static function defaultCuerpo(): string
    {
        return '<p style="text-align:center"><img class="auth-doc-logo" alt=""></p>'
            .'<p style="text-align:center"><strong>AUTORIZACIÓN</strong></p>'
            .'<p>Yo, {{propietario}}, identificada(o) con documento {{documento}}, titular de {{paciente}} (especie {{especie}}, raza {{raza}}, edad {{edad}}, sexo {{sexo}}), autorizo a {{clinica}} a realizar los procedimientos clínicos correspondientes.</p>'
            .'<ol>'
            .'<li>Autorizo al equipo médico a administrar el tratamiento que consideren necesario.</li>'
            .'<li>Declaro haber podido formular preguntas sobre el procedimiento.</li>'
            .'</ol>'
            .'<p style="text-align:center">Confirmo que he leído este documento bajo mi juicio.</p>'
            .'<p>{{ciudad}}, {{dia}} de {{mes_nombre}} de {{anio}}</p>';
    }

    /**
     * @return array<string, string>
     */
    public static function variablesFor(Consulta $consulta, Paciente $paciente, ?Propietario $owner): array
    {
        $clinic = ClinicSetting::current();
        $clinic->loadMissing('distritoModel');
        $clinicName = trim((string) ($clinic->nombre_comercial ?: $clinic->razon_social))
            ?: (string) config('app.name', 'Clínica');
        $ciudad = trim((string) ($clinic->distritoModel?->name ?? ''));

        $doc = trim(implode(' ', array_filter([
            $owner?->tipo_documento,
            $owner?->numero_documento,
        ])));

        $at = ($consulta->atendido_at ?? Carbon::now())->timezone((string) config('app.timezone'));
        $at->locale('es');

        return [
            'paciente' => $paciente->nombre,
            'especie' => trim((string) ($paciente->especie ?? '')) ?: '—',
            'raza' => trim((string) ($paciente->raza ?? '')) ?: '—',
            'edad' => self::edadTexto($paciente),
            'sexo' => trim((string) ($paciente->sexo ?? '')) ?: '—',
            'propietario' => $owner?->displayName() ?: '—',
            'documento' => $doc !== '' ? $doc : '—',
            'telefono' => trim((string) ($owner?->telefono ?? '')) ?: '—',
            'fecha' => $at->format('d/m/Y H:i'),
            'fecha_corta' => $at->format('d/m/Y'),
            'dia' => $at->format('j'),
            'mes' => $at->format('n'),
            'mes_nombre' => $at->translatedFormat('F'),
            'anio' => $at->format('Y'),
            'clinica' => $clinicName,
            'ciudad' => $ciudad !== '' ? $ciudad : '—',
            'veterinario' => trim((string) ($consulta->medico_tratante ?: $consulta->veterinario?->name)) ?: '—',
        ];
    }

    /**
     * @param  array<string, string>  $vars
     */
    public static function render(string $cuerpo, array $vars): string
    {
        $replaced = $cuerpo;
        foreach ($vars as $key => $value) {
            $replaced = str_replace('{{'.$key.'}}', $value, $replaced);
        }

        return $replaced;
    }

    public static function renderPlantilla(
        DocumentoAutorizacionPlantilla $plantilla,
        Consulta $consulta,
        Paciente $paciente,
        ?Propietario $owner,
    ): string {
        $vars = self::variablesFor($consulta, $paciente, $owner);
        $isHtml = self::looksLikeHtml($plantilla->cuerpo);
        if ($isHtml) {
            $escaped = [];
            foreach ($vars as $key => $value) {
                $escaped[$key] = e($value);
            }
            $vars = $escaped;
        }

        return self::prepareCuerpoHtml(self::render($plantilla->cuerpo, $vars));
    }

    public static function prepareCuerpoHtml(string $cuerpo, ?string $logoSrc = null): string
    {
        $cuerpo = self::repairUtf8($cuerpo);
        $cuerpo = str_replace('{{logo}}', '<img class="auth-doc-logo" alt="">', $cuerpo);

        return self::applyLogoSrc(self::toSafeHtml($cuerpo), $logoSrc ?? self::clinicLogoDataUri());
    }

    public static function looksLikeHtml(string $cuerpo): bool
    {
        return (bool) preg_match('/<\/?[a-z][\s\S]*>/i', $cuerpo);
    }

    public static function toSafeHtml(string $cuerpo): string
    {
        $cuerpo = self::repairUtf8($cuerpo);
        if (! self::looksLikeHtml($cuerpo)) {
            return nl2br(e($cuerpo), false);
        }

        return self::sanitizeHtml($cuerpo);
    }

    public static function sanitizeHtml(string $html): string
    {
        $html = trim(self::repairUtf8($html));
        if ($html === '') {
            return '';
        }

        $stripped = strip_tags($html, '<p><br><strong><b><em><i><u><ol><ul><li><h2><h3><div><span><img>');
        $root = self::loadRoot($stripped);
        if ($root === null) {
            return e(strip_tags($html));
        }

        self::sanitizeElement($root['element']);

        return self::saveRoot($root['dom'], $root['element']);
    }

    private static function sanitizeElement(DOMElement $el): void
    {
        $allowed = [
            'p' => ['style', 'align'],
            'div' => ['style', 'align'],
            'span' => ['style'],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'u' => [],
            'ol' => [],
            'ul' => [],
            'li' => [],
            'h2' => ['style', 'align'],
            'h3' => ['style', 'align'],
            'img' => ['class', 'alt'],
        ];

        $children = [];
        foreach ($el->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (! isset($allowed[$tag])) {
                while ($child->firstChild instanceof DOMNode) {
                    $el->insertBefore($child->firstChild, $child);
                }
                $el->removeChild($child);

                continue;
            }

            self::sanitizeElement($child);

            $keep = $allowed[$tag];
            $remove = [];
            if ($child->hasAttributes()) {
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    if (! in_array($name, $keep, true)) {
                        $remove[] = $name;

                        continue;
                    }
                    if ($name === 'style') {
                        $clean = self::sanitizeStyle($attr->value);
                        if ($clean === null) {
                            $remove[] = 'style';
                        } else {
                            $child->setAttribute('style', $clean);
                        }
                    }
                    if ($name === 'align') {
                        $v = strtolower(trim($attr->value));
                        if (! in_array($v, ['left', 'center', 'right', 'justify'], true)) {
                            $remove[] = 'align';
                        }
                    }
                }
            }
            foreach ($remove as $name) {
                $child->removeAttribute($name);
            }

            if ($tag === 'img') {
                $class = strtolower(trim($child->getAttribute('class')));
                if ($class !== 'auth-doc-logo') {
                    $el->removeChild($child);

                    continue;
                }
                $child->setAttribute('class', 'auth-doc-logo');
                $child->setAttribute('alt', '');
                $child->removeAttribute('src');
            }
        }
    }

    public static function applyLogoSrc(string $html, ?string $src): string
    {
        if (! str_contains($html, 'auth-doc-logo')) {
            return $html;
        }

        $parsed = self::loadRoot($html);
        if ($parsed === null) {
            return $html;
        }

        $imgs = [];
        foreach ($parsed['element']->getElementsByTagName('img') as $img) {
            $imgs[] = $img;
        }
        $src = is_string($src) && $src !== '' ? $src : null;
        foreach ($imgs as $img) {
            if (! $img instanceof DOMElement) {
                continue;
            }
            if (strtolower($img->getAttribute('class')) !== 'auth-doc-logo') {
                continue;
            }
            if ($src === null) {
                $img->parentNode?->removeChild($img);

                continue;
            }
            $img->setAttribute('src', $src);
            $img->setAttribute('alt', '');
        }

        return self::saveRoot($parsed['dom'], $parsed['element']);
    }

    public static function clinicLogoDataUri(): ?string
    {
        $clinic = ClinicSetting::current();
        $path = $clinic->logo_path;
        if ($path === null || $path === '') {
            return null;
        }
        $path = ltrim((string) $path, '/');
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }
        $binary = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path) ?? 'image/png';
        if (! is_string($mime) || ! str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) $binary);
    }

    private static function sanitizeStyle(string $style): ?string
    {
        if (! preg_match('/text-align\s*:\s*(left|center|right|justify)\s*;?/i', $style, $m)) {
            return null;
        }

        return 'text-align: '.strtolower($m[1]).';';
    }

    private static function edadTexto(Paciente $paciente): string
    {
        if ($paciente->fecha_nacimiento === null) {
            return '—';
        }

        $diff = $paciente->fecha_nacimiento->diff(Carbon::now());
        $parts = [];
        if ($diff->y > 0) {
            $parts[] = $diff->y === 1 ? '1 año' : $diff->y.' años';
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m === 1 ? '1 mes' : $diff->m.' meses';
        }
        if ($parts === [] && $diff->d > 0) {
            $parts[] = $diff->d === 1 ? '1 día' : $diff->d.' días';
        }

        return $parts !== [] ? implode(' y ', $parts) : '—';
    }

    /**
     * @return array{dom: DOMDocument, element: DOMElement}|null
     */
    private static function loadRoot(string $html): ?array
    {
        $encoded = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        $dom = new DOMDocument;
        $dom->encoding = 'UTF-8';
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="__auth_root">'.$encoded.'</div></body></html>',
            LIBXML_HTML_NODEFDTD | LIBXML_NOERROR,
        );
        libxml_clear_errors();
        $root = $dom->getElementById('__auth_root');
        if (! $root instanceof DOMElement) {
            return null;
        }

        return ['dom' => $dom, 'element' => $root];
    }

    private static function saveRoot(DOMDocument $dom, DOMElement $root): string
    {
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return mb_decode_numericentity($out, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
    }

    public static function repairUtf8(string $value): string
    {
        $current = $value;
        for ($i = 0; $i < 5; $i++) {
            $next = MojibakeFixer::repair($current);
            if (! is_string($next) || $next === $current) {
                break;
            }
            $current = $next;
        }

        return $current;
    }
}
