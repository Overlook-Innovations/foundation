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
     * Values corrected whatever the file already says.
     *
     * FILESYSTEM_DISK is the storage contract and has to be asserted rather
     * than defaulted. Laravel always ships the key in .env.example set to
     * "local", so a rule that only fills absent keys never fires — and the
     * application boots writing uploads to a container disk that is discarded
     * on the next deploy. That demos perfectly and then loses everything, which
     * is the failure this package exists to prevent and the first version of
     * this command shipped unable to prevent.
     *
     * Overwriting somebody's edit is the lesser risk. There is no legitimate
     * reason for an application the studio hosts to write to local disk, and
     * "private" is one of the two disk names Cloud matches its buckets against.
     *
     * @var array<string, string>
     */
    private const array ENVIRONMENT_ASSERTIONS = [
        'FILESYSTEM_DISK' => 'private',
    ];

    /**
     * Values added only when the file does not mention them at all.
     *
     * These agree with Laravel's own defaults today, and are written down so
     * they survive upstream changing its mind. An existing value is somebody's
     * decision and is left alone — which is a safe rule here precisely because
     * nothing below is a contract anyone else depends on.
     *
     * @var array<string, string>
     */
    private const array ENVIRONMENT_DEFAULTS = [
        'SESSION_DRIVER' => 'database',
        'QUEUE_CONNECTION' => 'database',
    ];

    public function handle(): int
    {
        $this->call('vendor:publish', array_filter([
            '--tag' => 'overlook-foundation',
            '--force' => $this->option('force') ?: null,
        ]));

        $this->applyEnvironmentValues();

        $this->components->info('Studio conventions applied.');

        return self::SUCCESS;
    }

    /**
     * Corrects the assertions and appends the missing defaults, in .env.example
     * and in .env when one exists.
     *
     * Edited in place rather than templated: the installer writes both files
     * and their contents move between Laravel releases, so replacing them
     * wholesale would put this package in the business of tracking upstream's
     * environment file — which is the drift the whole arrangement exists to
     * avoid.
     *
     * Idempotent either way. A second run finds every assertion already correct
     * and every default already present, and writes nothing.
     */
    private function applyEnvironmentValues(): void
    {
        foreach (['.env', '.env.example'] as $file) {
            $path = base_path($file);

            if (! File::exists($path)) {
                continue;
            }

            $original = File::get($path);
            $contents = $original;

            foreach (self::ENVIRONMENT_ASSERTIONS as $key => $value) {
                $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

                if (preg_match($pattern, $contents) === 1) {
                    $contents = (string) preg_replace($pattern, "{$key}={$value}", $contents);

                    continue;
                }

                $contents = rtrim($contents, "\n")."\n{$key}={$value}\n";
            }

            $additions = [];

            foreach (self::ENVIRONMENT_DEFAULTS as $key => $value) {
                if (preg_match('/^'.preg_quote($key, '/').'=/m', $contents) === 1) {
                    continue;
                }

                $additions[] = "{$key}={$value}";
            }

            if ($additions !== []) {
                $contents = rtrim($contents, "\n")."\n\n".implode("\n", $additions)."\n";
            }

            if ($contents === $original) {
                continue;
            }

            File::put($path, $contents);

            $this->components->task("Applied the studio's environment values to {$file}");
        }
    }
}
