<?php

namespace App\Core;

/**
 * Application — Owns the Container, boots Service Providers, runs the request.
 *
 * Application is a pure class with no global side effects.
 * Path constants are defined in bootstrap/constants.php before this
 * class is instantiated — Application neither defines nor depends on them.
 */
class Application
{
    private Container $container;

    /** @var ServiceProvider[] */
    private array $providers = [];


    public function __construct(private readonly string $basePath)
    {
        $this->container = new Container();

        // register the container and application themselves
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Application::class, $this);
    }


    public function registerProviders(array $providerClasses): void
    {
        foreach ($providerClasses as $class) {
            $provider = new $class($this->container);
            $provider->register();
            $this->providers[] = $provider;
        }

        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }


    /* Dispatch the current HTTP request through the Router */
    public function run(): void
    {
        $router = $this->container->make(Router::class);
        $request = $this->container->make(Request::class);
        $response = $this->container->make(Response::class);

        $router->dispatch($request, $response);
    }


    public function getContainer(): Container
    {
        return $this->container;
    }


    public function getBasePath(): string
    {
        return $this->basePath;
    }


}