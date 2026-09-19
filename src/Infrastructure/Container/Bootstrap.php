<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Container;

use MediCareMini\Application\Service\AuthService;
use MediCareMini\Application\Service\BookingService;
use MediCareMini\Application\Service\DashboardService;
use MediCareMini\Application\Service\LabReportService;
use MediCareMini\Application\Service\NotificationService;
use MediCareMini\Application\Service\PatientAuthService;
use MediCareMini\Application\Service\PaymentService;
use MediCareMini\Application\Service\PricingService;
use MediCareMini\Application\Service\SeoService;
use MediCareMini\Domain\Repository\AdvisoryLockInterface;
use MediCareMini\Domain\Repository\AuditLoggerInterface;
use MediCareMini\Domain\Repository\ClinicalNoteRepositoryInterface;
use MediCareMini\Domain\Repository\DiagnosticOrderRepositoryInterface;
use MediCareMini\Domain\Repository\EncounterRepositoryInterface;
use MediCareMini\Domain\Repository\LabCatalogRepositoryInterface;
use MediCareMini\Domain\Repository\LedgerRepositoryInterface;
use MediCareMini\Domain\Repository\NumberSequenceInterface;
use MediCareMini\Domain\Repository\PatientAccountRepositoryInterface;
use MediCareMini\Domain\Repository\PatientRepositoryInterface;
use MediCareMini\Domain\Repository\PrescriptionRepositoryInterface;
use MediCareMini\Domain\Repository\ReceivablePaymentRepositoryInterface;
use MediCareMini\Domain\Repository\StaffDirectoryInterface;
use MediCareMini\Domain\Repository\TransactionManagerInterface;
use MediCareMini\Domain\Repository\WardLocationRepositoryInterface;
use MediCareMini\Domain\Repository\WardScopeRepositoryInterface;
use MediCareMini\Domain\Services\BillingService;
use MediCareMini\Domain\Services\EncounterService;
use MediCareMini\Domain\Services\PatientDeduplicationService;
use MediCareMini\Domain\Services\WardScopeService;
use MediCareMini\Domain\Services\QueueService;
use MediCareMini\Infrastructure\Mail\Mailer;
use MediCareMini\Infrastructure\Mail\MailQueue;
use MediCareMini\Infrastructure\Mail\MailRenderer;
use MediCareMini\Infrastructure\Persistence\AppointmentRepository;
use MediCareMini\Infrastructure\Persistence\ArticleRepository;
use MediCareMini\Infrastructure\Persistence\AuditLogger;
use MediCareMini\Infrastructure\Persistence\ClinicalNoteRepository;
use MediCareMini\Infrastructure\Persistence\Database;
use MediCareMini\Infrastructure\Persistence\DiagnosticOrderRepository;
use MediCareMini\Infrastructure\Persistence\DoctorRepository;
use MediCareMini\Infrastructure\Persistence\EncounterRepository;
use MediCareMini\Infrastructure\Persistence\FacilityRepository;
use MediCareMini\Infrastructure\Persistence\InquiryRepository;
use MediCareMini\Infrastructure\Persistence\LabCatalogRepository;
use MediCareMini\Infrastructure\Persistence\LedgerRepository;
use MediCareMini\Infrastructure\Persistence\PackageRepository;
use MediCareMini\Infrastructure\Persistence\PatientAccountRepository;
use MediCareMini\Infrastructure\Persistence\PatientRepository;
use MediCareMini\Infrastructure\Persistence\PaymentRepository;
use MediCareMini\Infrastructure\Persistence\PdoAdvisoryLock;
use MediCareMini\Infrastructure\Persistence\PdoNumberSequence;
use MediCareMini\Infrastructure\Persistence\PdoTransactionManager;
use MediCareMini\Infrastructure\Persistence\PrescriptionRepository;
use MediCareMini\Infrastructure\Persistence\ReceivablePaymentRepository;
use MediCareMini\Infrastructure\Persistence\RoleRepository;
use MediCareMini\Infrastructure\Persistence\ServiceRepository;
use MediCareMini\Infrastructure\Persistence\SettingsRepository;
use MediCareMini\Infrastructure\Persistence\StaffDirectory;
use MediCareMini\Infrastructure\Persistence\UserRepository;
use MediCareMini\Infrastructure\Persistence\WardLocationRepository;
use MediCareMini\Infrastructure\Persistence\WardScopeRepository;
use MediCareMini\Infrastructure\Security\Csrf;
use MediCareMini\Infrastructure\Security\Encryptor;
use MediCareMini\Infrastructure\Security\PasswordHasher;
use MediCareMini\Infrastructure\Security\RateLimiter;
use MediCareMini\Infrastructure\Security\SessionManager;
use MediCareMini\Infrastructure\Storage\FileUploader;
use MediCareMini\Infrastructure\Support\BrandResolver;
use MediCareMini\Infrastructure\Support\BrandWriter;
use MediCareMini\Infrastructure\Support\Config;
use MediCareMini\Infrastructure\Support\Env;
use MediCareMini\Infrastructure\Support\Logger;
use MediCareMini\Infrastructure\Support\Translator;
use MediCareMini\Presentation\View\View;

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
            LedgerRepository::class,
            ReceivablePaymentRepository::class,
            RoleRepository::class,
            WardScopeRepository::class,
            LabCatalogRepository::class,
        ] as $repository) {
            $container->singleton(
                $repository,
                static fn (Container $c): object => new $repository($c->get(Database::class)),
            );
        }

        $container->singleton(PatientRepository::class, static fn (Container $c): PatientRepository
            => new PatientRepository($c->get(Database::class), $c->get(Encryptor::class)));

        $container->singleton(PatientAccountRepository::class, static fn (Container $c): PatientAccountRepository
            => new PatientAccountRepository($c->get(Database::class)));

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
            PatientAccountRepositoryInterface::class,
            static fn (Container $c): PatientAccountRepositoryInterface => $c->get(PatientAccountRepository::class),
        );
        $container->singleton(
            WardLocationRepositoryInterface::class,
            static fn (Container $c): WardLocationRepositoryInterface => $c->get(WardLocationRepository::class),
        );
        $container->singleton(
            WardScopeRepositoryInterface::class,
            static fn (Container $c): WardScopeRepositoryInterface => $c->get(WardScopeRepository::class),
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
            LabCatalogRepositoryInterface::class,
            static fn (Container $c): LabCatalogRepositoryInterface => $c->get(LabCatalogRepository::class),
        );
        $container->singleton(
            PrescriptionRepositoryInterface::class,
            static fn (Container $c): PrescriptionRepositoryInterface => $c->get(PrescriptionRepository::class),
        );
        $container->singleton(
            LedgerRepositoryInterface::class,
            static fn (Container $c): LedgerRepositoryInterface => $c->get(LedgerRepository::class),
        );
        $container->singleton(
            ReceivablePaymentRepositoryInterface::class,
            static fn (Container $c): ReceivablePaymentRepositoryInterface => $c->get(ReceivablePaymentRepository::class),
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

        $container->singleton(BrandResolver::class, static fn (Container $c): BrandResolver
            => new BrandResolver($config, $c->get(SettingsRepository::class)));

        $container->singleton(BrandWriter::class, static fn (Container $c): BrandWriter
            => new BrandWriter($c->get(BrandResolver::class)));

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
                $c->get(AuditLoggerInterface::class),
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

        $container->singleton(WardScopeService::class, static fn (Container $c): WardScopeService
            => new WardScopeService(
                $c->get(WardScopeRepositoryInterface::class),
                $c->get(EncounterRepositoryInterface::class),
                $c->get(WardLocationRepositoryInterface::class),
            ));

        $container->singleton(BillingService::class, static fn (Container $c): BillingService
            => new BillingService(
                $c->get(LedgerRepositoryInterface::class),
                $c->get(ReceivablePaymentRepositoryInterface::class),
                $c->get(EncounterRepositoryInterface::class),
                $c->get(WardLocationRepositoryInterface::class),
                $c->get(StaffDirectoryInterface::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(AuditLoggerInterface::class),
            ));

        $container->singleton(QueueService::class, static fn (Container $c): QueueService
            => new QueueService(
                $c->get(EncounterRepositoryInterface::class),
                $c->get(AuditLoggerInterface::class),
            ));

        // Laboratory reporting: the one place a lab result crosses
        // between ciphertext and a usable object (migration 012).
        $container->singleton(LabReportService::class, static fn (Container $c): LabReportService
            => new LabReportService(
                $c->get(DiagnosticOrderRepositoryInterface::class),
                $c->get(LabCatalogRepositoryInterface::class),
                $c->get(NumberSequenceInterface::class),
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

        $container->singleton(PatientAuthService::class, static fn (Container $c): PatientAuthService => new PatientAuthService(
            patients: $c->get(PatientRepositoryInterface::class),
            accounts: $c->get(PatientAccountRepositoryInterface::class),
            hasher:   $c->get(PasswordHasher::class),
            session:  $c->get(SessionManager::class),
            limiter:  $c->get(RateLimiter::class),
            audit:    $c->get(AuditLogger::class),
            config:   $config,
            logger:   $c->get(Logger::class),
        ));

        $container->singleton(DashboardService::class, static fn (Container $c): DashboardService => new DashboardService(
            db:           $c->get(Database::class),
            appointments: $c->get(AppointmentRepository::class),
            payments:     $c->get(PaymentRepository::class),
            doctors:      $c->get(DoctorRepository::class),
            inquiries:    $c->get(InquiryRepository::class),
            encounters:   $c->get(EncounterRepository::class),
            config:       $config,
        ));

        $container->singleton(SeoService::class, static function (Container $c) use ($config): SeoService {
            $settings = $c->get(SettingsRepository::class);

            return new SeoService($config, $settings, $c->get(Translator::class), $c->get(BrandResolver::class));
        });

        // --- Site assistant ----------------------------------------------

        $container->singleton(
            \MediCareMini\Infrastructure\Ai\ChatProvider::class,
            static fn (Container $c): \MediCareMini\Infrastructure\Ai\ChatProvider => new \MediCareMini\Infrastructure\Ai\ChatProvider(
                logger:         $c->get(Logger::class)->withChannel('ai'),
                baseUrl:        \MediCareMini\Infrastructure\Support\Env::bool('AI_ENABLED', false)
                    ? (\MediCareMini\Infrastructure\Support\Env::get('AI_BASE_URL') ?? '')
                    : '',
                apiKey:         \MediCareMini\Infrastructure\Support\Env::get('AI_API_KEY') ?? '',
                model:          \MediCareMini\Infrastructure\Support\Env::get('AI_MODEL') ?? '',
                timeoutSeconds: \MediCareMini\Infrastructure\Support\Env::int('AI_TIMEOUT', 20),
            ),
        );

        $container->singleton(
            \MediCareMini\Application\Service\SiteGuide::class,
            static fn (Container $c): \MediCareMini\Application\Service\SiteGuide => new \MediCareMini\Application\Service\SiteGuide(
                $c->get(ServiceRepository::class),
                $c->get(DoctorRepository::class),
                $c->get(PackageRepository::class),
                $c->get(FacilityRepository::class),
                $c->get(ArticleRepository::class),
                $c->get(SettingsRepository::class),
                $c->get(BrandResolver::class),
                $c->get(Translator::class),
                $config,
            ),
        );

        $container->singleton(
            \MediCareMini\Application\Service\ChatService::class,
            static fn (Container $c): \MediCareMini\Application\Service\ChatService => new \MediCareMini\Application\Service\ChatService(
                $c->get(\MediCareMini\Infrastructure\Ai\ChatProvider::class),
                $c->get(\MediCareMini\Application\Service\SiteGuide::class),
                $c->get(SettingsRepository::class),
                $c->get(Translator::class),
            ),
        );

        // --- Presentation ------------------------------------------------

        $container->singleton(View::class, static fn (Container $c): View => new View(
            viewPath:   $config->path('resources/views'),
            translator: $c->get(Translator::class),
            config:     $config,
            brand:      $c->get(BrandResolver::class)->resolve(),
            csrf:       $cli ? null : $c->get(Csrf::class),
        ));

        return $container;
    }
}
