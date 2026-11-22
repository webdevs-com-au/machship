<?php

namespace WebdevsComAu\MachShip\Data\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * A file returned by MachShip: item labels, consignment notes, manifests,
 * POD attachments. Return it from a controller to stream it to the browser.
 */
final class Document implements Responsable
{
    public function __construct(
        public readonly string $contents,
        public readonly string $fileName,
        public readonly string $contentType = 'application/pdf',
    ) {
    }

    /**
     * From a MachShip FileInfo object: {fileName, contentType, content (base64)}.
     *
     * @param  array{fileName?: string|null, contentType?: string|null, content?: string|null}  $info
     */
    public static function fromFileInfo(array $info, string $fallbackName = 'document.pdf'): self
    {
        return new self(
            base64_decode((string) ($info['content'] ?? ''), true) ?: '',
            $info['fileName'] ?? $fallbackName,
            $info['contentType'] ?? 'application/pdf',
        );
    }

    public static function fromResponse(HttpResponse $response, ?string $fallbackName = null): self
    {
        $contentType = $response->header('Content-Type') ?: 'application/octet-stream';
        $fileName = self::fileNameFromDisposition($response->header('Content-Disposition'))
            ?? $fallbackName
            ?? ('machship'.(str_contains($contentType, 'zip') ? '.zip' : '.pdf'));

        return new self($response->body(), $fileName, strtok($contentType, ';') ?: $contentType);
    }

    public function isPdf(): bool
    {
        return str_contains($this->contentType, 'pdf');
    }

    public function isZip(): bool
    {
        return str_contains($this->contentType, 'zip');
    }

    public function size(): int
    {
        return strlen($this->contents);
    }

    public function base64(): string
    {
        return base64_encode($this->contents);
    }

    /**
     * Store on a filesystem disk. Returns the stored path.
     */
    public function store(?string $directory = null, ?string $disk = null, ?string $fileName = null): string
    {
        $directory ??= (string) config('machship.labels.path', 'machship/labels');
        $path = trim($directory, '/').'/'.($fileName ?? $this->fileName);

        Storage::disk($disk ?? config('machship.labels.disk'))->put(ltrim($path, '/'), $this->contents);

        return ltrim($path, '/');
    }

    /**
     * Write to an absolute local path.
     */
    public function saveTo(string $path): string
    {
        file_put_contents($path, $this->contents);

        return $path;
    }

    public function download(?string $fileName = null): Response
    {
        return $this->respond(HeaderUtils::DISPOSITION_ATTACHMENT, $fileName);
    }

    public function inline(?string $fileName = null): Response
    {
        return $this->respond(HeaderUtils::DISPOSITION_INLINE, $fileName);
    }

    public function toResponse($request): Response
    {
        return $this->inline();
    }

    private function respond(string $disposition, ?string $fileName): Response
    {
        $name = $fileName ?? $this->fileName;

        return new Response($this->contents, 200, [
            'Content-Type' => $this->contentType,
            'Content-Length' => (string) $this->size(),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $disposition,
                $name,
                preg_replace('/[^\x20-\x7e]/', '_', $name) ?: 'document',
            ),
        ]);
    }

    private static function fileNameFromDisposition(?string $header): ?string
    {
        if (! $header) {
            return null;
        }

        if (preg_match("/filename\*=(?:UTF-8'')?([^;]+)/i", $header, $m)) {
            return rawurldecode(trim($m[1], " \"'"));
        }

        if (preg_match('/filename="?([^";]+)"?/i', $header, $m)) {
            return trim($m[1]);
        }

        return null;
    }
}
