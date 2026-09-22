<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class WordAttachmentPreviewer
{
    public const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const OLE_HEADER = "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1";

    public function preview(string $content, string $filename): array
    {
        $format = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($format, ['doc', 'docx'], true)) {
            throw new WordPreviewException('Предпросмотр доступен для документов DOC и DOCX.');
        }

        if ($content === '' || strlen($content) > self::MAX_FILE_BYTES) {
            throw new WordPreviewException('Для предпросмотра выберите непустой Word-документ размером до 10 МБ.');
        }

        if ($format === 'docx' && str_starts_with($content, self::OLE_HEADER)) {
            throw new WordPreviewException('Документ защищён паролем. Для просмотра нужна незашифрованная копия.');
        }

        if (($format === 'doc' && ! str_starts_with($content, self::OLE_HEADER)) || ($format === 'docx' && ! str_starts_with($content, "PK\x03\x04"))) {
            throw new WordPreviewException('Содержимое вложения не соответствует формату Word.');
        }

        $php = (new PhpExecutableFinder)->find(false);

        if (! $php || ! function_exists('proc_open')) {
            throw new WordPreviewException('Предпросмотр Word временно недоступен.', 503);
        }

        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'mail-word-'.bin2hex(random_bytes(16));

        if (! mkdir($directory, 0700)) {
            throw new WordPreviewException('Не удалось подготовить Word-документ к просмотру.', 503);
        }

        try {
            $path = $directory.'/document.'.$format;
            if (file_put_contents($path, $content) !== strlen($content) || ! chmod($path, 0600)) {
                throw new WordPreviewException('Не удалось подготовить Word-документ к просмотру.', 503);
            }

            $options = [
                'memory_limit=128M',
                'max_execution_time=10',
                'display_errors=0',
                'log_errors=0',
                'allow_url_fopen=0',
                'allow_url_include=0',
                'auto_prepend_file=',
                'auto_append_file=',
                'enable_dl=0',
                'ffi.enable=0',
                'sys_temp_dir='.$directory,
                'open_basedir='.implode(PATH_SEPARATOR, [base_path('vendor'), app_path('Services/Mail'), $directory]),
                'disable_functions=exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec,fsockopen,pfsockopen,stream_socket_client,stream_socket_server,socket_create,dl,mail,symlink,link',
            ];
            $command = [$php];
            foreach ($options as $option) {
                $command[] = '-d';
                $command[] = $option;
            }
            $command = [...$command, app_path('Services/Mail/WordPreview/worker.php'), $path, $format];

            // Symfony normally inherits the server environment; remove credentials and app configuration.
            $environment = array_fill_keys(array_unique([...array_keys(getenv()), ...array_keys($_ENV), ...array_keys($_SERVER)]), false);
            $process = new Process($command, $directory, $environment, null, 12);
            $output = '';
            $process->run(function (string $type, string $buffer) use (&$output): void {
                if ($type === Process::OUT) {
                    $output .= $buffer;
                }
                if (strlen($output) > 2 * 1024 * 1024) {
                    throw new WordPreviewException('В Word-документе слишком много текста для предпросмотра.');
                }
            });

            $parsed = json_decode($output, true);

            if (! $process->isSuccessful() || ! is_array($parsed) || ! isset($parsed['blocks'])) {
                throw new WordPreviewException(is_string($parsed['error'] ?? null) ? $parsed['error'] : 'Не удалось прочитать Word-документ. Возможно, файл повреждён или слишком сложен.');
            }

            return $this->render($parsed, $format);
        } catch (WordPreviewException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new WordPreviewException('Не удалось прочитать Word-документ за отведённое время. Попробуйте скачать оригинал.');
        } finally {
            if (isset($process) && $process->isRunning()) {
                $process->stop(0.2);
            }
            File::deleteDirectory($directory);
        }
    }

    private function render(array $parsed, string $format): array
    {
        $html = '';
        $parts = [];

        foreach ($parsed['blocks'] as $block) {
            if (($block['type'] ?? null) === 'table') {
                $html .= '<div class="table-wrap"><table>';
                $rows = [];
                foreach ($block['rows'] as $cells) {
                    $html .= '<tr>';
                    foreach ($cells as $cell) {
                        $html .= '<td>'.nl2br(e($cell)).'</td>';
                    }
                    $html .= '</tr>';
                    $rows[] = implode("\t", $cells);
                }
                $html .= '</table></div>';
                $parts[] = implode("\n", $rows);
            } else {
                $html .= '<p>'.e($block['text']).'</p>';
                $parts[] = $block['text'];
            }
        }

        $text = trim(implode("\n\n", $parts));
        $document = '<!doctype html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; base-uri \'none\'; form-action \'none\'">'
            .'<style>html{color-scheme:light}body{margin:0;padding:24px;background:#eef2f6;color:#25354a;font:14px/1.65 Arial,sans-serif}article{max-width:820px;min-height:180px;margin:auto;padding:28px;background:white;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 3px 14px #20354a08}p{margin:0 0 14px;overflow-wrap:anywhere;white-space:pre-wrap}p:last-child{margin-bottom:0}.table-wrap{overflow:auto;margin:14px 0}table{width:100%;border-collapse:collapse}td{padding:9px 12px;vertical-align:top;border:1px solid #dce3ec;min-width:80px;overflow-wrap:anywhere}tr:nth-child(odd){background:#f8fafc}@media(max-width:480px){body{padding:10px}article{padding:16px}}</style>'
            .'</head><body><article>'.$html.'</article></body></html>';

        return ['format' => $format, 'html' => $document, 'text' => $text, 'has_text' => $text !== '', 'truncated' => (bool) ($parsed['truncated'] ?? false)];
    }
}
