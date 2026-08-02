<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * Turns two production settings that are usually guessed into measurements:
 * how many PHP files OPcache has to hold, and how much memory one request
 * actually costs.
 *
 * The plan is explicit that PHP-FPM workers must be sized from measured
 * memory rather than set to "as many as possible", and that OPcache must be
 * sized to the file count. Neither number can be honestly written into an ini
 * file without running this against the release being deployed.
 */
#[Signature('ops:capacity {--ram= : MB of RAM available to PHP-FPM workers}')]
#[Description('Measure the PHP file count and per-request memory, then report the settings they imply')]
class MeasureProductionCapacity extends Command
{
    /**
     * Requests the measurement replays. The heaviest public read models, not
     * the cheapest: a worker has to survive the worst of them, not the mean.
     *
     * @var list<string>
     */
    private const PROBES = [
        '/api/v1/home?locale=ru',
        '/api/v1/news?locale=ru',
        '/api/v1/regions?locale=ru',
        '/api/v1/menu?locale=ru',
    ];

    /** Запас к измеренному числу файлов: релиз растёт между заменами ini. */
    private const FILE_HEADROOM = 1.2;

    public function handle(): int
    {
        $files = $this->countPhpFiles();
        $requiredFiles = (int) ceil($files * self::FILE_HEADROOM);
        $configuredFiles = (int) config('production.opcache.max_accelerated_files');

        $baseline = memory_get_peak_usage(true);
        $perRequest = $this->measureRequestMemory();
        $configuredChildren = (int) config('production.php_fpm_max_children');

        $this->components->twoColumnDetail('PHP-файлов в релизе', (string) $files);
        $this->components->twoColumnDetail(
            'opcache.max_accelerated_files (нужно / настроено)',
            "{$requiredFiles} / {$configuredFiles}",
        );
        $this->components->twoColumnDetail(
            'Память: базовая загрузка / пик на запрос',
            $this->mb($baseline).' / '.$this->mb($perRequest),
        );

        if ($configuredFiles < $requiredFiles) {
            $this->components->error(
                "opcache.max_accelerated_files={$configuredFiles} ниже измеренного числа файлов с запасом ({$requiredFiles}): OPcache начнёт вытеснять классы и компилировать их заново на каждом запросе.",
            );
        }

        $ram = (int) $this->option('ram');

        if ($ram > 0) {
            $recommended = max(1, (int) floor($ram / max($this->megabytes($perRequest), 1)));
            $this->components->twoColumnDetail(
                "pm.max_children при {$ram} MB (расчёт / настроено)",
                "{$recommended} / {$configuredChildren}",
            );

            if ($configuredChildren > $recommended) {
                $this->components->error(
                    "PHP_FPM_MAX_CHILDREN={$configuredChildren} больше, чем помещается в {$ram} MB при измеренном пике {$this->mb($perRequest)} на запрос: под нагрузкой это своп или OOM, а не пропускная способность.",
                );

                return self::FAILURE;
            }
        }

        // Замер сделан в CLI: тот же код и те же классы, но без накладных
        // расходов FPM-воркера. Число — нижняя граница, поэтому в ini его
        // берут с запасом, а не впритык.
        $this->components->info(
            'Измерено в CLI-процессе: реальный воркер FPM потребляет не меньше. Числа — нижняя граница для ini.',
        );

        return $configuredFiles < $requiredFiles ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Файлы считаются в тех каталогах, которые действительно попадают в
     * релиз. `tests` и dev-зависимости в production не устанавливаются, но
     * если команду запустили на dev-установке — цифра выйдет выше реальной,
     * то есть в безопасную сторону.
     */
    private function countPhpFiles(): int
    {
        $count = 0;

        foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'vendor'] as $directory) {
            $path = base_path($directory);

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Пик памяти после обработки представительных запросов через настоящий
     * HTTP-ядро приложения — а не оценка «примерно столько же, сколько у
     * соседнего проекта».
     */
    private function measureRequestMemory(): int
    {
        $kernel = app(Kernel::class);

        foreach (self::PROBES as $uri) {
            $kernel->handle(Request::create($uri, 'GET'));
        }

        return memory_get_peak_usage(true);
    }

    private function mb(int $bytes): string
    {
        return $this->megabytes($bytes).' MB';
    }

    private function megabytes(int $bytes): int
    {
        return (int) ceil($bytes / 1048576);
    }
}
