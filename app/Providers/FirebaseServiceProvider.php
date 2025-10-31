<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
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
        // Always register PSR-7 and PSR-17 interfaces (needed by Laravel container)
        $this->registerPsrInterfaces();

        // Always register Firebase bindings - they will handle missing configuration gracefully
        $this->registerFirebaseBindings();
    }

    /**
     * Register PSR-7 and PSR-17 interfaces
     */
    private function registerPsrInterfaces()
    {
        // Bind PSR-7 UriInterface to Guzzle's Uri implementation so the container
        // can instantiate it when a class type-hints Psr\Http\Message\UriInterface.
        if (! $this->app->bound(Psr7UriInterface::class)) {
            $this->app->bind(Psr7UriInterface::class, function ($app) {
                return new GuzzleUri('');
            });
        }

        // Bind PSR-17 UriFactoryInterface to the Guzzle implementation provided
        // by http-interop/http-factory-guzzle so factories can be resolved.
        if (! $this->app->bound(Psr17UriFactoryInterface::class)) {
            if (class_exists(GuzzleUriFactory::class)) {
                $this->app->bind(Psr17UriFactoryInterface::class, function ($app) {
                    return new GuzzleUriFactory();
                });
            } else {
                // Fallback implementation if GuzzleUriFactory is not available
                $this->app->bind(Psr17UriFactoryInterface::class, function ($app) {
                    return new class implements Psr17UriFactoryInterface {
                        public function createUri(string $uri = ''): Psr7UriInterface {
                            return new GuzzleUri($uri);
                        }
                    };
                });
            }
        }
    }

    /**
     * Register Firebase-specific bindings
     */
    private function registerFirebaseBindings()
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
                            // Silently continue if URL rewriting fails
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
                if (!function_exists('get_option')) {
                    throw new \RuntimeException('get_option function not available - Firebase not configured.');
                }
                
                $databaseUrl = get_option('firebase_database_url');
                if (empty($databaseUrl)) {
                    throw new \RuntimeException('FIREBASE_DATABASE_URL is not configured.');
                }

                return KreaitUrlBuilder::create($databaseUrl);
            });
        }

        // Ensure a Kreait Database instance is bound using the discovered
        // credentials (path or decoded array). This avoids permission issues
        // when the package's provider resolved credentials earlier.
        if (! $this->app->bound(KreaitDatabase::class)) {
            $this->app->singleton(KreaitDatabase::class, function () {
                if (!function_exists('get_option')) {
                    throw new \RuntimeException('get_option function not available - Firebase not configured.');
                }

                $factory = new KreaitFactory();

                // Prefer decoded credentials set in config (array). Fall back to
                // FIREBASE_CREDENTIALS env which may be a path or an array.
                $configCredentials = config('firebase.projects.app.credentials');
                $envCredentials = storage_path(get_option('firebase_json'));

                if (is_array($configCredentials)) {
                    $factory = $factory->withServiceAccount($configCredentials);
                } elseif (is_string($envCredentials) && file_exists($envCredentials)) {
                    $factory = $factory->withServiceAccount($envCredentials);
                } elseif (is_array($envCredentials)) {
                    $factory = $factory->withServiceAccount($envCredentials);
                } else {
                    throw new \RuntimeException('No valid Firebase credentials found.');
                }

                $databaseUrl = get_option('firebase_database_url');
                if (empty($databaseUrl)) {
                    throw new \RuntimeException('Firebase database URL not configured.');
                }
                
                $factory = $factory->withDatabaseUri($databaseUrl);

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
        if(env('APP_INSTALLED', false) == true){
            $firebaseCredentials = storage_path(get_option('firebase_json'));
            if (!file_exists($firebaseCredentials)) {
                \Log::error('Firebase credentials file not found at: ' . $firebaseCredentials);
            } elseif (!is_readable($firebaseCredentials)) {
                \Log::error('Firebase credentials file is not readable: ' . $firebaseCredentials);
            } else {
                $content = @file_get_contents($firebaseCredentials);
                $decoded = @json_decode($content, true);
                if (is_array($decoded)) {
                    config(['firebase.projects.app.credentials' => $decoded]);
                } else {
                    \Log::error('Firebase credentials file could not be decoded as JSON: ' . $firebaseCredentials);
                }
            }
        }
    }
}
