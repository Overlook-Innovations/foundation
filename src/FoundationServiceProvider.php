<?php

namespace Overlook\Foundation;

use Illuminate\Support\ServiceProvider;
use Overlook\Foundation\Console\InstallCommand;

/**
 * The studio's conventions, as behaviour rather than as files.
 *
 * This is the whole reason the scaffolder installs a package instead of copying
 * a directory over the generated application. A copied file is frozen the day
 * it was written — which is exactly what went wrong with the template this
 * replaced — and it reaches only the next project, never the forty already
 * built. A package is a version number, so a fix here becomes a Renovate pull
 * request on every client repository.
 *
 * The rule that keeps that true: never publish a file Laravel owns. Anything
 * that can be expressed as configuration set at boot belongs here, not in a
 * stub. Only files the framework has no opinion about — CI workflows, editor
 * settings — are published, because nothing upstream can drift underneath them.
 */
class FoundationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerObjectStorage();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);

            $this->publishes([
                __DIR__.'/../stubs' => base_path(),
            ], 'overlook-foundation');
        }
    }

    /**
     * Declares the two disks the hosting platform attaches buckets to.
     *
     * Laravel Cloud matches on the disk *name* and replaces the whole entry at
     * runtime — credentials, bucket, endpoint and public URL — from the
     * LARAVEL_CLOUD_DISK_CONFIG blob it hands the container. So all an
     * application has to supply is the names, and these two are the contract
     * the studio's provisioner expects. Rename one here and buckets attach to a
     * disk nothing writes to.
     *
     * Set from here rather than published as a config/filesystems.php, which
     * would freeze that file at whatever Laravel shipped the day it was copied.
     *
     * Two disks rather than one because Cloud's object storage is Cloudflare
     * R2, which fixes visibility on the bucket and rejects a per-object ACL — so
     * one bucket cannot hold both a public avatar and a private signed
     * document.
     *
     * The public one is deliberately not called "public". Laravel ships a local
     * disk under that name behind the storage:link symlink, and shadowing it
     * leaves storage:link silently doing nothing.
     */
    private function registerObjectStorage(): void
    {
        config([
            'filesystems.disks.private' => [
                'driver' => 's3',
                'visibility' => 'private',
                'throw' => false,
            ],
            'filesystems.disks.media' => [
                'driver' => 's3',
                'visibility' => 'public',
                'throw' => false,
            ],
        ]);
    }
}
