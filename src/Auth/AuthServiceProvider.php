<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Auth;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $dispatcher): void
    {
        $dispatcher->listen(RegisterDirectiveNamespaces::class, static fn (): string => __NAMESPACE__);
    }

    public static function guard(): ?string
    {
        $config = Container::getInstance()->make(ConfigRepository::class);
        $lighthouseGuard = $config->get('lighthouse.guard');
        $guards = $config->get('auth.guards');

        return isset($guards[$lighthouseGuard])
            ? $lighthouseGuard
            : $config->get('auth.defaults.guard');
    }
}
