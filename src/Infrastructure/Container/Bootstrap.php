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
use Aster\Domain\Repository\AdvisoryLockInterface;
use Aster\Domain\Repository\AuditLoggerInterface;
use Aster\Domain\Repository\ClinicalNoteRepositoryInterface;
use Aster\Domain\Repository\DiagnosticOrderRepositoryInterface;
use Aster\Domain\Repository\EncounterRepositoryInterface;
use Aster\Domain\Repository\NumberSequenceInterface;
use Aster\Domain\Repository\PatientRepositoryInterface;
use Aster\Domain\Repository\PrescriptionRepositoryInterface;
use Aster\Domain\Repository\StaffDirectoryInterface;
use Aster\Domain\Repository\TransactionManagerInterface;
use Aster\Domain\Repository\WardLocationRepositoryInterface;
use Aster\Domain\Services\EncounterService;
use Aster\Domain\Services\PatientDeduplicationService;
use Aster\Infrastructure\Mail\Mailer;
use Aster\Infrastructure\Mail\MailQueue;
use Aster\Infrastructure\Mail\MailRenderer;
use Aster\Infrastructure\Persistence\AppointmentRepository;
use Aster\Infrastructure\Persistence\ArticleRepository;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\ClinicalNoteRepository;
use Aster\Infrastructure\Persistence\Database;
use Aster\Infrastructure\Persistence\DiagnosticOrderRepository;
use Aster\Infrastructure\Persistence\DoctorRepository;
use Aster\Infrastructure\Persistence\EncounterRepository;
use Aster\Infrastructure\Persistence\FacilityRepository;
use Aster\Infrastructure\Persistence\InquiryRepository;
use Aster\Infrastructure\Persistence\PackageRepository;
use Aster\Infrastructure\Persistence\PatientRepository;
use Aster\Infrastructure\Persistence\PaymentRepository;
use Aster\Infrastructure\Persistence\PdoAdvisoryLock;
use Aster\Infrastructure\Persistence\PdoNumberSequence;
use Aster\Infrastructure\Persistence\PdoTransactionManager;
use Aster\Infrastructure\Persistence\PrescriptionRepository;
use Aster\Infrastructure\Persistence\ServiceRepository;
use Aster\Infrastructure\Persistence\SettingsRepository;
use Aster\Infrastructure\Persistence\StaffDirectory;
use Aster\Infrastructure\Persistence\UserRepository;
use Aster\Infrastructure\Persistence\WardLocationRepository;
use Aster\Infrastructure\Security\Csrf;
use Aster\Infrastructure\Security\Encryptor;
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
            PdoNumberSequence::class,
            WardLocationRepository::class,
            EncounterRepository::class,
            PdoTransactionManager::class,
            PdoAdvisoryLock::class,
            StaffDirectory::class,
            PrescriptionRepository::class,
        ] as $repository) {
            $container->singleton(
                $repository,
                static fn (Container $c): object => new $repository($c->get(Database::class)),
            );
        }

        $container->singleton(PatientRepository::class, static fn (Container $c): PatientRepository
            => new PatientRepository($c->get(Database::class), $c->get(Encryptor::class)));

        $container->singleton(ClinicalNoteRepository::class, static fn (Container $c): ClinicalNoteRepository
            => new ClinicalNoteRepository($c->get(Database::class), $c->get(Encryptor::class)));

        $container->singleton(DiagnosticOrderRepository::class, static fn (Container $c): DiagnosticOrderRepository
            => new DiagnosticOrderRepository($c->get(Database::class), $c->get(Encryptor::class)));

        // Domain services depend on the interface (ports-in-Domain,
        // PDO-in-Infrastructure), so each resolves to the same singleton
        // instance as its concrete class above.
        $container->singleton(
            NumberSequenceInterface::class,
            static fn (Container $c): NumberSequenceInterface => $c->get(PdoNumberSequence::class),
        );
        $container->singleton(
            PatientRepositoryInterface::class,
            static fn (Container $c): PatientRepositoryInterface => $c->get(PatientRepository::class),
        );
        $container->singleton(
            WardLocationRepositoryInterface::class,
            static fn (Container $c): WardLocationRepositoryInterface => $c->get(WardLocationRepository::class),
        );
        $container->singleton(
            EncounterRepositoryInterface::class,
            static fn (Container $c): EncounterRepositoryInterface => $c->get(EncounterRepository::class),
        );
        $container->singleton(
            TransactionManagerInterface::class,
            static fn (Container $c): TransactionManagerInterface => $c->get(PdoTransactionManager::class),
        );
        $container->singleton(
            AdvisoryLockInterface::class,
            static fn (Container $c): AdvisoryLockInterface => $c->get(PdoAdvisoryLock::class),
        );
        $container->singleton(
            StaffDirectoryInterface::class,
            static fn (Container $c): StaffDirectoryInterface => $c->get(StaffDirectory::class),
        );
        $container->singleton(
            ClinicalNoteRepositoryInterface::class,
            static fn (Container $c): ClinicalNoteRepositoryInterface => $c->get(ClinicalNoteRepository::class),
        );
        $container->singleton(
            DiagnosticOrderRepositoryInterface::class,
            static fn (Container $c): DiagnosticOrderRepositoryInterface => $c->get(DiagnosticOrderRepository::class),
        );
        $container->singleton(
            PrescriptionRepositoryInterface::class,
            static fn (Container $c): PrescriptionRepositoryInterface => $c->get(PrescriptionRepository::class),
        );
        // AuditLogger already implements AuditLoggerInterface directly -
        // no separate concrete-vs-interface pair needed, unlike the
        // repositories above (those have a Pdo*/*Repository split because
        // their concrete class name differs from the interface; here it
        // is the SAME object, just also usable as the Domain port).
        $container->singleton(
            AuditLoggerInterface::class,
            static fn (Container $c): AuditLoggerInterface => $c->get(AuditLogger::class),
        );

        // --- Security ----------------------------------------------------

        $container->singleton(Encryptor::class, static fn (Container $c): Encryptor
            => new Encryptor($c->get(Config::class)->appKey));

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

        // --- Domain services (Phase II) -----------------------------------
        //
        // Live in Domain\Services per the FRS's own stated paths, but depend
        // only on the interfaces registered above - never on Database, PDO,
        // or a concrete repository class - per the "ports in Domain, PDO in
        // Infrastructure" decision.

        $container->singleton(PatientDeduplicationService::class, static fn (Container $c): PatientDeduplicationService
            => new PatientDeduplicationService(
                $c->get(PatientRepositoryInterface::class),
                $c->get(NumberSequenceInterface::class),
                $c->get(AdvisoryLockInterface::class),
            ));

        $container->singleton(EncounterService::class, static fn (Container $c): EncounterService
            => new EncounterService(
                $c->get(EncounterRepositoryInterface::class),
                $c->get(PatientRepositoryInterface::class),
                $c->get(WardLocationRepositoryInterface::class),
                $c->get(StaffDirectoryInterface::class),
                $c->get(NumberSequenceInterface::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(AuditLoggerInterface::class),
            ));

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
