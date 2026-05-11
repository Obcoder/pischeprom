<?php

namespace App\Jobs;

use App\Models\GoodMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessGoodVideoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public int $mediaId
    ) {}

    public function handle(): void
    {
        $media = GoodMedia::query()->findOrFail($this->mediaId);

        if ($media->type !== 'video') {
            return;
        }

        $media->update([
                           'processing_status' => 'processing',
                           'processing_error' => null,
                           'is_processed' => false,
                       ]);

        $diskName = $media->disk ?: 'yandex';
        $disk = Storage::disk($diskName);

        $workDir = storage_path("app/video-processing/{$media->id}");

        if (!is_dir($workDir)) {
            mkdir($workDir, 0755, true);
        }

        $inputPath = "{$workDir}/original.{$media->extension}";
        $posterLocalPath = "{$workDir}/poster.jpg";
        $mp4LocalPath = "{$workDir}/processed.mp4";

        try {
            $this->downloadFromStorage($diskName, $media->path, $inputPath);

            $probe = $this->ffprobe($inputPath);

            $duration = $this->extractDuration($probe);
            $width = $this->extractWidth($probe);
            $height = $this->extractHeight($probe);

            $this->makePoster($inputPath, $posterLocalPath);
            $this->makeMp4($inputPath, $mp4LocalPath);

            $baseName = pathinfo($media->file_name ?: $media->path, PATHINFO_FILENAME);
            $safeBaseName = Str::slug($baseName) ?: "video-{$media->id}";

            $posterPath = "goods/{$media->good_id}/videos/posters/{$safeBaseName}-poster.jpg";
            $mp4Path = "goods/{$media->good_id}/videos/processed/{$safeBaseName}-720p.mp4";

            $this->uploadToStorage($diskName, $posterPath, $posterLocalPath, 'image/jpeg');
            $this->uploadToStorage($diskName, $mp4Path, $mp4LocalPath, 'video/mp4');

            $media->update([
                               'poster_path' => $posterPath,
                               'poster_url' => $this->publicUrl($posterPath),

                               'video_mp4_path' => $mp4Path,
                               'video_mp4_url' => $this->publicUrl($mp4Path),

                               'width' => $width,
                               'height' => $height,
                               'duration_seconds' => $duration,

                               'is_processed' => true,
                               'processing_status' => 'done',
                               'processing_error' => null,

                               'meta' => [
                                   'ffprobe' => $probe,
                                   'processed_at' => now()->toDateTimeString(),
                               ],
                           ]);
        } catch (Throwable $e) {
            $media->update([
                               'processing_status' => 'failed',
                               'processing_error' => $e->getMessage(),
                               'is_processed' => false,
                           ]);

            throw $e;
        } finally {
            $this->deleteDirectory($workDir);
        }
    }

    private function downloadFromStorage(string $diskName, string $storagePath, string $localPath): void
    {
        $stream = Storage::disk($diskName)->readStream($storagePath);

        if (!$stream) {
            throw new \RuntimeException("Cannot read video from storage: {$storagePath}");
        }

        $target = fopen($localPath, 'w');

        if (!$target) {
            fclose($stream);
            throw new \RuntimeException("Cannot create local file: {$localPath}");
        }

        stream_copy_to_stream($stream, $target);

        fclose($stream);
        fclose($target);
    }

    private function uploadToStorage(
        string $diskName,
        string $storagePath,
        string $localPath,
        string $contentType
    ): void {
        $stream = fopen($localPath, 'r');

        if (!$stream) {
            throw new \RuntimeException("Cannot open local file for upload: {$localPath}");
        }

        Storage::disk($diskName)->put($storagePath, $stream, [
            'ContentType' => $contentType,
        ]);

        fclose($stream);
    }

    private function ffprobe(string $inputPath): array
    {
        $process = new Process([
                                   'ffprobe',
                                   '-v',
                                   'quiet',
                                   '-print_format',
                                   'json',
                                   '-show_format',
                                   '-show_streams',
                                   $inputPath,
                               ]);

        $process->setTimeout(120);
        $process->mustRun();

        return json_decode($process->getOutput(), true) ?: [];
    }

    private function makePoster(string $inputPath, string $posterPath): void
    {
        $process = new Process([
                                   'ffmpeg',
                                   '-y',
                                   '-ss',
                                   '00:00:01',
                                   '-i',
                                   $inputPath,
                                   '-frames:v',
                                   '1',
                                   '-vf',
                                   'scale=1280:-2:force_original_aspect_ratio=decrease',
                                   '-q:v',
                                   '3',
                                   $posterPath,
                               ]);

        $process->setTimeout(180);

        try {
            $process->mustRun();
        } catch (Throwable) {
            $fallback = new Process([
                                        'ffmpeg',
                                        '-y',
                                        '-i',
                                        $inputPath,
                                        '-frames:v',
                                        '1',
                                        '-vf',
                                        'scale=1280:-2:force_original_aspect_ratio=decrease',
                                        '-q:v',
                                        '3',
                                        $posterPath,
                                    ]);

            $fallback->setTimeout(180);
            $fallback->mustRun();
        }
    }

    private function makeMp4(string $inputPath, string $mp4Path): void
    {
        $process = new Process([
                                   'ffmpeg',
                                   '-y',
                                   '-i',
                                   $inputPath,
                                   '-vf',
                                   'scale=1280:-2:force_original_aspect_ratio=decrease',
                                   '-c:v',
                                   'libx264',
                                   '-preset',
                                   'veryfast',
                                   '-crf',
                                   '26',
                                   '-movflags',
                                   '+faststart',
                                   '-c:a',
                                   'aac',
                                   '-b:a',
                                   '128k',
                                   $mp4Path,
                               ]);

        $process->setTimeout(900);
        $process->mustRun();
    }

    private function extractDuration(array $probe): ?float
    {
        $duration = $probe['format']['duration'] ?? null;

        return $duration !== null ? round((float) $duration, 3) : null;
    }

    private function extractWidth(array $probe): ?int
    {
        $stream = collect($probe['streams'] ?? [])
            ->firstWhere('codec_type', 'video');

        return $stream['width'] ?? null;
    }

    private function extractHeight(array $probe): ?int
    {
        $stream = collect($probe['streams'] ?? [])
            ->firstWhere('codec_type', 'video');

        return $stream['height'] ?? null;
    }

    private function publicUrl(string $path): string
    {
        $bucket = config('filesystems.disks.yandex.bucket');

        return "https://storage.yandexcloud.net/{$bucket}/{$path}";
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $file = "{$path}/{$item}";

            if (is_dir($file)) {
                $this->deleteDirectory($file);
            } else {
                @unlink($file);
            }
        }

        @rmdir($path);
    }
}
