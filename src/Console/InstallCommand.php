<?php

namespace Overlook\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Applies the conventions that cannot be expressed as runtime configuration.
 *
 * Two kinds of work, and the difference matters. Publishing stubs is safe
 * wholesale, because those files have no upstream to drift from — nothing in
 * Laravel owns a CI workflow or an editor config. Editing framework files is
 * not, so each edit here is deliberate, idempotent, and a debt: anything that
 * can be moved into the service provider should be.
 *
 * Idempotent throughout. The scaffolder runs it once, but a client repository
 * runs it again after every Renovate bump of this package, and a second run
 * must be a no-op rather than a duplicate.
 */
class InstallCommand extends Command
{
    protected $signature = 'overlook:install {--force : Overwrite published files that have been edited}';

    protected $description = "Apply the studio's conventions to this application";

    /**
     * Environment defaults the platform does not inject.
     *
     * Laravel Cloud sets the database, cache and storage credentials itself, so
     * these are only the values it cannot know. FILESYSTEM_DISK is here because
     * an application that boots pointing at "local" writes uploads to a
     * container disk that is discarded on the next deploy — which demos
     * perfectly and then loses everything.
     *
     * @var array<string, string>
     */
    private const array ENVIRONMENT_DEFAULTS = [
        'FILESYSTEM_DISK' => 'private',
        'SESSION_DRIVER' => 'database',
        'QUEUE_CONNECTION' => 'database',
    ];

    public function handle(): int
    {
        $this->call('vendor:publish', array_filter([
            '--tag' => 'overlook-foundation',
            '--force' => $this->option('force') ?: null,
        ]));

        $this->applyEnvironmentDefaults();

        $this->components->info('Studio conventions applied.');

        return self::SUCCESS;
    }

    /**
     * Appends the defaults to .env.example, and to .env when one exists.
     *
     * Appended rather than templated: the installer writes both files and their
     * contents move between Laravel releases, so replacing them would put this
     * package in the business of tracking upstream's environment file — which
     * is the drift the whole arrangement exists to avoid.
     *
     * A key already present is left exactly as it is, whatever its value.
     * Somebody who changed it meant to.
     */
    private function applyEnvironmentDefaults(): void
    {
        foreach (['.env', '.env.example'] as $file) {
            $path = base_path($file);

            if (! File::exists($path)) {
                continue;
            }

            $contents = File::get($path);
            $additions = [];

            foreach (self::ENVIRONMENT_DEFAULTS as $key => $value) {
                if (preg_match('/^'.preg_quote($key, '/').'=/m', $contents) === 1) {
                    continue;
                }

                $additions[] = "{$key}={$value}";
            }

            if ($additions === []) {
                continue;
            }

            File::put($path, rtrim($contents, "\n")."\n\n".implode("\n", $additions)."\n");

            $this->components->task("Added ".count($additions)." defaults to {$file}");
        }
    }
}
