<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Kreait\Firebase\Database\UrlBuilder as KreaitUrlBuilder;
use Kreait\Firebase\Factory as KreaitFactory;
use Kreait\Firebase\Database as KreaitDatabase;
use Psr\Http\Message\UriInterface as Psr7UriInterface;
use Psr\Http\Factory\UriFactoryInterface as Psr17UriFactoryInterface;
use GuzzleHttp\Psr7\Uri as GuzzleUri;
use Http\Factory\Guzzle\UriFactory as GuzzleUriFactory;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register Firebase-specific container bindings.
     */
    public function register()
    {
        // Bind Guzzle ClientInterface with a handler that appends .json to
        if (! $this->app->bound(GuzzleClientInterface::class)) {
            $this->app->singleton(GuzzleClientInterface::class, function () {
                $stack = \GuzzleHttp\HandlerStack::create();

                $firebaseHost = parse_url(get_option('firebase_database_url') ?: '', PHP_URL_HOST);

                $stack->push(function (callable $handler) use ($firebaseHost) {
                    return function ($request, array $options) use ($handler, $firebaseHost) {
                        try {
                            $uri = $request->getUri();

                            if ($firebaseHost && $uri->getHost() === $firebaseHost) {
                                $path = $uri->getPath() ?: '/';

                                if (! str_ends_with($path, '.json')) {
                                    $newPath = rtrim($path, '/') . '.json';
                                    $uri = $uri->withPath($newPath);
                                    $request = $request->withUri($uri);
                                }
                            }
                        } catch (\Throwable $e) {
                            \Log::warning('Failed to rewrite Firebase request URI for .json suffix', ['error' => $e->getMessage()]);
                        }

                        return $handler($request, $options);
                    };
                });

                return new GuzzleClient([
                    'timeout' => 5.0,
                    'handler' => $stack,
                ]);
            });
        }

        // Bind Kreait UrlBuilder using configured database URL.
        if (! $this->app->bound(KreaitUrlBuilder::class)) {
            $this->app->bind(KreaitUrlBuilder::class, function () {
                $databaseUrl = get_option('firebase_database_url');
                if (empty($databaseUrl)) {
                    throw new \RuntimeException('FIREBASE_DATABASE_URL is not configured.');
                }

                return KreaitUrlBuilder::create($databaseUrl);
            });
        }

        // Bind PSR-7 UriInterface to Guzzle's Uri implementation so the container
        // can instantiate it when a class type-hints Psr\Http\Message\UriInterface.
        if (! $this->app->bound(Psr7UriInterface::class)) {
            $this->app->bind(Psr7UriInterface::class, function () {
                return new GuzzleUri('');
            });
        }

        // Bind PSR-17 UriFactoryInterface to the Guzzle implementation provided
        // by http-interop/http-factory-guzzle so factories can be resolved.
        if (class_exists(GuzzleUriFactory::class) && ! $this->app->bound(Psr17UriFactoryInterface::class)) {
            $this->app->bind(Psr17UriFactoryInterface::class, GuzzleUriFactory::class);
        }

        // Ensure a Kreait Database instance is bound using the discovered
        // credentials (path or decoded array). This avoids permission issues
        // when the package's provider resolved credentials earlier.
        if (! $this->app->bound(KreaitDatabase::class)) {
            $this->app->singleton(KreaitDatabase::class, function () {

                $factory = new KreaitFactory();

                // Prefer decoded credentials set in config (array). Fall back to
                // FIREBASE_CREDENTIALS env which may be a path or an array.
                $configCredentials = config('firebase.projects.app.credentials');
                $envCredentials = 'public/uploads/files/' . get_option('firebase_json');

                if (is_array($configCredentials)) {
                    $factory = $factory->withServiceAccount($configCredentials);
                } elseif (is_string($envCredentials) && file_exists($envCredentials)) {
                    $factory = $factory->withServiceAccount($envCredentials);
                } elseif (is_array($envCredentials)) {
                    $factory = $factory->withServiceAccount($envCredentials);
                }

                $databaseUrl = get_option('firebase_database_url');
                if ($databaseUrl) {
                    $factory = $factory->withDatabaseUri($databaseUrl);
                }

                return $factory->createDatabase();
            });
        }
    }

    /**
     * Bootstrap any Firebase-specific services after the container is ready.
     * We perform runtime config overrides here so the 'config' binding is
     * available.
     */
    public function boot()
    {
        $firebaseCredentials = 'public/uploads/files/' . get_option('firebase_json');
        if ($firebaseCredentials && file_exists($firebaseCredentials) && is_readable($firebaseCredentials)) {
            $content = @file_get_contents($firebaseCredentials);
            $decoded = @json_decode($content, true);
            if (is_array($decoded)) {
                // Overwrite the runtime config value so Kreait receives the array
                config(['firebase.projects.app.credentials' => $decoded]);
            }
        }
    }
}
