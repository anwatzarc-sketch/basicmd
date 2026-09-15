<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Container;

use Aster\Application\Service\AuthService;
use Aster\Application\Service\BookingService;
use Aster\Application\Service\DashboardService;
use Aster\Application\Service\NotificationService;
use Aster\Application\Service\PaymentService;
use Aster\Application\Service\PricingService;
use Aster\Application\Service\SeoService;
use Aster\Infrastructure\Mail\Mailer;
use Aster\Infrastructure\Mail\MailQueue;
use Aster\Infrastructure\Mail\MailRenderer;
use Aster\Infrastructure\Persistence\AppointmentRepository;
use Aster\Infrastructure\Persistence\ArticleRepository;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\Database;
use Aster\Infrastructure\Persistence\DoctorRepository;
use Aster\Infrastructure\Persistence\FacilityRepository;
use Aster\Infrastructure\Persistence\InquiryRepository;
use Aster\Infrastructure\Persistence\PackageRepository;
use Aster\Infrastructure\Persistence\PaymentRepository;
use Aster\Infrastructure\Persistence\ServiceRepository;
use Aster\Infrastructure\Persistence\SettingsRepository;
use Aster\Infrastructure\Persistence\UserRepository;
use Aster\Infrastructure\Security\Csrf;
use Aster\Infrastructure\Security\PasswordHasher;
use Aster\Infrastructure\Security\RateLimiter;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Storage\FileUploader;
use Aster\Infrastructure\Support\Config;
use Aster\Infrastructure\Support\Env;
use Aster\Infrastructure\Support\Logger;
use Aster\Infrastructure\Support\Translator;
use Aster\Presentation\View\View;

/**
 * Wires the application.
 *
 * One place that knows how every object is constructed. Both the web front
 * controller and the CLI workers boot through here, so a cron job and a web
 * request cannot end up with differently-configured services - which is how
 * "it works on the site but the reminder emails are wrong" happens.
 */
final class Bootstrap
{
    public static function boot(string $basePath, bool $cli = false): Container
    {
        Env::load($basePath . DIRECTORY_SEPARATOR . '.env');

        $config = Config::fromEnv($basePath);

        date_default_timezone_set($config->timezone->getName());

        // Internal encoding must be UTF-8 or every mb_* call on Amharic text
        // silently operates on the wrong character boundaries.
        mb_internal_encoding('UTF-8');

        $container = new Container();
        $container->instance(Config::class, $config);

        // --- Support -----------------------------------------------------

        $container->singleton(Logger::class, static fn (Container $c): Logger => new Logger(
            directory:     $config->path('storage/logs'),
            minimumLevel:  $config->logLevel(),
            retentionDays: $config->logRetentionDays(),
            channel:       $cli ? 'cli' : 'app',
        ));

        $container->singleton(Translator::class, static fn (Container $c): Translator => new Translator(
            langPath:       $config->path('lang'),
            locale:         $config->defaultLocale,
            timezone:       $config->timezone,
            collectMissing: $config->appDebug,
        ));

        // --- Persistence -------------------------------------------------

        $container->singleton(Database::class, static fn (): Database => Database::fromEnv());

        $container->singleton(SettingsRepository::class, static fn (Container $c): SettingsRepository
            => new SettingsRepository($c->get(Database::class)));

        $container->singleton(AuditLogger::class, static fn (Container $c): AuditLogger => new AuditLogger(
            $c->get(Database::class),
            $c->get(Logger::class),
        ));

        foreach ([
            AppointmentRepository::class,
            DoctorRepository::class,
            ServiceRepository::class,
            PackageRepository::class,
            PaymentRepository::class,
            ArticleRepository::class,
            InquiryRepository::class,
            FacilityRepository::class,
            UserRepository::class,
        ] as $repository) {
            $container->singleton(
                $repository,
                static fn (Container $c): object => new $repository($c->get(Database::class)),
            );
        }

        // --- Security ----------------------------------------------------

        $container->singleton(SessionManager::class, static fn (): SessionManager => new SessionManager($config));

        $container->singleton(Csrf::class, static fn (Container $c): Csrf
            => new Csrf($c->get(SessionManager::class)));

        $container->singleton(PasswordHasher::class, static fn (): PasswordHasher
            => new PasswordHasher($config->argonOptions()));

        $container->singleton(RateLimiter::class, static fn (Container $c): RateLimiter
            => new RateLimiter($c->get(Database::class)));

        $container->singleton(FileUploader::class, static fn (Container $c): FileUploader => new FileUploader(
            $config,
            $c->get(Logger::class),
        ));

        // --- Mail --------------------------------------------------------

        $container->singleton(Mailer::class, static fn (Container $c): Mailer => new Mailer(
            $c->get(Logger::class)->withChannel('mail'),
            $config->path('storage/logs'),
        ));

        $container->singleton(MailQueue::class, static fn (Container $c): MailQueue => new MailQueue(
            $c->get(Database::class),
            $c->get(Mailer::class),
            $c->get(Logger::class)->withChannel('mail'),
        ));

        $container->singleton(MailRenderer::class, static function (Container $c) use ($config): MailRenderer {
            $settings = $c->get(SettingsRepository::class);

            return new MailRenderer(
                translator:   $c->get(Translator::class),
                clinicName:   $settings->string('clinic_name', $config->appName),
                appUrl:       $config->appUrl,
                supportPhone: $settings->string('phone_primary', ''),
                supportEmail: $settings->string('email_public', ''),
            );
        });

        // --- Application services ----------------------------------------

        $container->singleton(PricingService::class, static function (Container $c) use ($config): PricingService {
            // The setting overrides .env so Finance can change the express
            // premium from the admin screen without a redeploy.
            $settings = $c->get(SettingsRepository::class);
            $rate     = $settings->float('express_surcharge_rate', $config->expressSurchargeRate());

            return new PricingService($rate);
        });

        $container->singleton(NotificationService::class, static function (Container $c) use ($config): NotificationService {
            $settings = $c->get(SettingsRepository::class);

            return new NotificationService(
                queue:         $c->get(MailQueue::class),
                renderer:      $c->get(MailRenderer::class),
                translator:    $c->get(Translator::class),
                config:        $config,
                clinicName:    $settings->string('clinic_name', $config->appName),
                clinicAddress: $settings->string('address', 'Bole Sub-city, Addis Ababa'),
                supportPhone:  $settings->string('phone_primary', ''),
            );
        });

        $container->singleton(BookingService::class, static fn (Container $c): BookingService => new BookingService(
            appointments:  $c->get(AppointmentRepository::class),
            doctors:       $c->get(DoctorRepository::class),
            services:      $c->get(ServiceRepository::class),
            packages:      $c->get(PackageRepository::class),
            pricing:       $c->get(PricingService::class),
            notifications: $c->get(NotificationService::class),
            audit:         $c->get(AuditLogger::class),
            config:        $config,
            logger:        $c->get(Logger::class),
        ));

        $container->singleton(PaymentService::class, static fn (Container $c): PaymentService => new PaymentService(
            payments:      $c->get(PaymentRepository::class),
            appointments:  $c->get(AppointmentRepository::class),
            uploader:      $c->get(FileUploader::class),
            notifications: $c->get(NotificationService::class),
            audit:         $c->get(AuditLogger::class),
            logger:        $c->get(Logger::class),
        ));

        $container->singleton(AuthService::class, static fn (Container $c): AuthService => new AuthService(
            users:   $c->get(UserRepository::class),
            hasher:  $c->get(PasswordHasher::class),
            session: $c->get(SessionManager::class),
            csrf:    $c->get(Csrf::class),
            limiter: $c->get(RateLimiter::class),
            audit:   $c->get(AuditLogger::class),
            config:  $config,
            logger:  $c->get(Logger::class),
        ));

        $container->singleton(DashboardService::class, static fn (Container $c): DashboardService => new DashboardService(
            db:           $c->get(Database::class),
            appointments: $c->get(AppointmentRepository::class),
            payments:     $c->get(PaymentRepository::class),
            doctors:      $c->get(DoctorRepository::class),
            inquiries:    $c->get(InquiryRepository::class),
            config:       $config,
        ));

        $container->singleton(SeoService::class, static function (Container $c) use ($config): SeoService {
            $settings = $c->get(SettingsRepository::class);

            return new SeoService($config, $settings, $c->get(Translator::class));
        });

        // --- Presentation ------------------------------------------------

        $container->singleton(View::class, static fn (Container $c): View => new View(
            viewPath:   $config->path('resources/views'),
            translator: $c->get(Translator::class),
            config:     $config,
            csrf:       $cli ? null : $c->get(Csrf::class),
        ));

        return $container;
    }
}
