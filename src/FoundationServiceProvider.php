<?php

namespace Overlook\Foundation;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Overlook\Foundation\Console\InstallCommand;
use Overlook\Foundation\Http\Middleware\InjectReviewWidget;

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
        /**
         * Merged, never published. The application gets the values without
         * getting a copy of the file, so this package can still change them —
         * which is the whole arrangement, applied to its own configuration.
         */
        $this->mergeConfigFrom(__DIR__.'/../config/foundation.php', 'foundation');

        $this->registerObjectStorage();
    }

    public function boot(): void
    {
        $this->registerReviewWidget();

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);

            $this->publishes([
                __DIR__.'/../stubs' => base_path(),
            ], 'overlook-foundation');
        }
    }

    /**
     * Puts the client review widget on every page the application serves.
     *
     * Registered from here because a generated application owns its
     * bootstrap/app.php and the studio never edits it — the same reason the tag
     * is injected rather than placed in a layout.
     *
     * Added to the HTTP kernel rather than to the router's "web" group, which
     * looks like the obvious home and silently does not work. The kernel
     * replaces every group wholesale in syncMiddlewareToRouter() when it is
     * constructed, and it is constructed lazily — so a group entry added while
     * providers boot is discarded before the first request is dispatched, with
     * nothing logged and the group still reading correctly if something asks it
     * in between. That cost a while to find; it is written down so it is not
     * found twice.
     *
     * Global middleware is also the position this wants. It wraps everything,
     * so it is the last to see the response and injects into the document that
     * is really sent. The content type guard keeps it off API traffic.
     */
    private function registerReviewWidget(): void
    {
        $this->callAfterResolving(HttpKernel::class, function (HttpKernel $kernel): void {
            $kernel->pushMiddleware(InjectReviewWidget::class);
        });
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
     * The s3 driver these name is supplied by this package's own requirement on
     * league/flysystem-aws-s3-v3. Declaring the disks without it is what made
     * every provision fail at its first deploy: Cloud attaches the buckets, sees
     * an application that cannot read them, and refuses.
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
