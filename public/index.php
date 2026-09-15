<?php

declare(strict_types=1);

/**
 * Front controller.
 *
 * The single entry point for every request. Everything above this file in the
 * deployment - nginx, Apache - only needs to route unknown paths here; nothing
 * else in the project is web-reachable, which is why src/, storage/, lang/ and
 * the .env file all sit outside public/.
 */

use Aster\Application\Service\AuthService;
use Aster\Application\Service\BookingService;
use Aster\Application\Service\DashboardService;
use Aster\Application\Service\PaymentService;
use Aster\Application\Service\SeoService;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Repository\EncounterRepositoryInterface;
use Aster\Domain\Repository\LedgerRepositoryInterface;
use Aster\Domain\Repository\NumberSequenceInterface;
use Aster\Domain\Repository\ReceivablePaymentRepositoryInterface;
use Aster\Domain\Repository\WardLocationRepositoryInterface;
use Aster\Domain\Services\BillingService;
use Aster\Domain\Services\EncounterService;
use Aster\Domain\Services\PatientDeduplicationService;
use Aster\Domain\Services\QueueService;
use Aster\Infrastructure\Container\Bootstrap;
use Aster\Infrastructure\Container\Container;
use Aster\Infrastructure\Mail\MailQueue;
use Aster\Infrastructure\Mail\Mailer;
use Aster\Infrastructure\Persistence\AppointmentRepository;
use Aster\Infrastructure\Persistence\ArticleRepository;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\DoctorRepository;
use Aster\Infrastructure\Persistence\FacilityRepository;
use Aster\Infrastructure\Persistence\InquiryRepository;
use Aster\Infrastructure\Persistence\PackageRepository;
use Aster\Infrastructure\Persistence\PatientRepository;
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
use Aster\Infrastructure\Support\Logger;
use Aster\Presentation\Controller\Admin as AdminController;
use Aster\Presentation\Controller\Web as WebController;
use Aster\Presentation\Http\ErrorHandler;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\Http\Router;
use Aster\Presentation\Middleware\Authenticate;
use Aster\Presentation\Middleware\Authorize;
use Aster\Presentation\Middleware\SecurityHeaders;
use Aster\Presentation\Middleware\SetLocale;
use Aster\Presentation\Middleware\VerifyCsrf;
use Aster\Presentation\View\View;

$basePath = dirname(__DIR__);

// ---------------------------------------------------------------------
//  Static files under the PHP development server
//
//  `php -S host:port -t public public/index.php` sends EVERY request here,
//  including /dist/css/app.min.css. Returning false hands the request back to
//  the built-in server so it serves the file from disk - without this, the
//  whole site renders unstyled locally.
//
//  Guarded on the cli-server SAPI, so it is inert under PHP-FPM, where nginx
//  and Apache serve these paths directly and never reach this file.
// ---------------------------------------------------------------------
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $candidate   = realpath(__DIR__ . urldecode($requestPath));
    $webroot     = realpath(__DIR__);

    // realpath() resolves any ../ before the prefix check, so a crafted path
    // cannot escape public/ and serve .env or a source file.
    if (
        $candidate !== false
        && $webroot !== false
        && $candidate !== $webroot
        && str_starts_with($candidate, $webroot . DIRECTORY_SEPARATOR)
        && is_file($candidate)
        // index.php itself must still run, not be served as source.
        && $candidate !== __FILE__
    ) {
        return false;
    }
}

require $basePath . '/vendor/autoload.php';

// ---------------------------------------------------------------------
//  Boot
// ---------------------------------------------------------------------

try {
    $container = Bootstrap::boot($basePath);
} catch (Throwable $e) {
    // Before the logger exists there is nowhere to record this, so the
    // message goes to PHP's own error log and the visitor sees nothing.
    error_log('Aster boot failure: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><title>Service unavailable</title>'
        . '<div style="font-family:system-ui;padding:48px;text-align:center">'
        . '<h1>Service temporarily unavailable</h1>'
        . '<p>We are working to restore service. Please try again shortly.</p></div>';
    exit;
}

/** @var Config $config */
$config = $container->get(Config::class);

/** @var Logger $logger */
$logger = $container->get(Logger::class);

// Errors are never printed; the ErrorHandler decides what a visitor sees.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$request = Request::capture();

// Host-header check: blocks cache-poisoning and password-reset link
// poisoning by refusing requests claiming an unexpected host.
$trustedHosts = $config->trustedHosts();

if ($config->isProduction() && $trustedHosts !== [] && !in_array($request->host(), $trustedHosts, true)) {
    $logger->warning('Rejected untrusted host header', ['host' => $request->host()]);
    http_response_code(400);
    exit('Invalid host.');
}

// One CSP nonce per request, shared with the templates.
$GLOBALS['aster_csp_nonce'] = SecurityHeaders::generateNonce();

/** @var SessionManager $session */
$session = $container->get(SessionManager::class);
$session->start();

/** @var View $view */
$view = $container->get(View::class);

$errorHandler = new ErrorHandler($view, $logger, $config);
$errorHandler->register();

// ---------------------------------------------------------------------
//  Controllers
// ---------------------------------------------------------------------

$make = static function (string $class, array $extra = []) use ($container, $view, $session, $config): object {
    return new $class($view, $session, $config, ...$extra);
};

$home = $make(WebController\HomeController::class, [
    $container->get(ServiceRepository::class),
    $container->get(DoctorRepository::class),
    $container->get(PackageRepository::class),
    $container->get(FacilityRepository::class),
    $container->get(ArticleRepository::class),
    $container->get(SettingsRepository::class),
    $container->get(SeoService::class),
]);

$booking = $make(WebController\BookingController::class, [
    $container->get(BookingService::class),
    $container->get(PaymentService::class),
    $container->get(AppointmentRepository::class),
    $container->get(PaymentRepository::class),
    $container->get(ServiceRepository::class),
    $container->get(PackageRepository::class),
    $container->get(DoctorRepository::class),
    $container->get(SettingsRepository::class),
    $container->get(RateLimiter::class),
    $container->get(SeoService::class),
]);

$content = $make(WebController\ContentController::class, [
    $container->get(ServiceRepository::class),
    $container->get(DoctorRepository::class),
    $container->get(PackageRepository::class),
    $container->get(ArticleRepository::class),
    $container->get(SettingsRepository::class),
    $container->get(SeoService::class),
]);

$contact = $make(WebController\ContactController::class, [
    $container->get(InquiryRepository::class),
    $container->get(\Aster\Application\Service\NotificationService::class),
    $container->get(SettingsRepository::class),
    $container->get(RateLimiter::class),
    $container->get(AuditLogger::class),
    $logger,
    $container->get(SeoService::class),
]);

$sitemap = new WebController\SitemapController(
    $config,
    $container->get(ArticleRepository::class),
    $container->get(ServiceRepository::class),
    $container->get(DoctorRepository::class),
    $container->get(FileUploader::class),
);

$auth = $make(AdminController\AuthController::class, [$container->get(AuthService::class)]);

$dashboard = $make(AdminController\DashboardController::class, [
    $container->get(DashboardService::class),
    $container->get(DoctorRepository::class),
]);

$appointments = $make(AdminController\AppointmentController::class, [
    $container->get(AppointmentRepository::class),
    $container->get(PaymentRepository::class),
    $container->get(DoctorRepository::class),
    $container->get(ServiceRepository::class),
    $container->get(PackageRepository::class),
    $container->get(BookingService::class),
    $container->get(AuditLogger::class),
    $container->get(PatientDeduplicationService::class),
    $container->get(EncounterService::class),
    $container->get(EncounterRepositoryInterface::class),
]);

$payments = $make(AdminController\PaymentController::class, [
    $container->get(PaymentRepository::class),
    $container->get(AppointmentRepository::class),
    $container->get(PaymentService::class),
    $container->get(AuditLogger::class),
]);

$doctorsAdmin = $make(AdminController\DoctorController::class, [
    $container->get(DoctorRepository::class),
    $container->get(FileUploader::class),
    $container->get(AuditLogger::class),
]);

$catalog = $make(AdminController\CatalogController::class, [
    $container->get(ServiceRepository::class),
    $container->get(PackageRepository::class),
    $container->get(FacilityRepository::class),
    $container->get(FileUploader::class),
    $container->get(AuditLogger::class),
]);

$articlesAdmin = $make(AdminController\ArticleController::class, [
    $container->get(ArticleRepository::class),
    $container->get(DoctorRepository::class),
    $container->get(FileUploader::class),
    $container->get(AuditLogger::class),
]);

$inquiriesAdmin = $make(AdminController\InquiryController::class, [
    $container->get(InquiryRepository::class),
    $container->get(AuditLogger::class),
]);

$usersAdmin = $make(AdminController\UserController::class, [
    $container->get(UserRepository::class),
    $container->get(DoctorRepository::class),
    $container->get(PasswordHasher::class),
    $container->get(AuditLogger::class),
]);

$settingsAdmin = $make(AdminController\SettingsController::class, [
    $container->get(SettingsRepository::class),
    $container->get(MailQueue::class),
    $container->get(Mailer::class),
    $container->get(AuditLogger::class),
]);

// --- Phase II -----------------------------------------------------------

$patientsAdmin = $make(AdminController\PatientController::class, [
    $container->get(PatientRepository::class),
    $container->get(PatientDeduplicationService::class),
    $container->get(EncounterRepositoryInterface::class),
]);

$encountersAdmin = $make(AdminController\EncounterController::class, [
    $container->get(EncounterRepositoryInterface::class),
    $container->get(WardLocationRepositoryInterface::class),
    $container->get(LedgerRepositoryInterface::class),
    $container->get(ReceivablePaymentRepositoryInterface::class),
    $container->get(PatientRepository::class),
    $container->get(UserRepository::class),
    $container->get(EncounterService::class),
    $container->get(BillingService::class),
    $container->get(QueueService::class),
]);

$billingAdmin = $make(AdminController\BillingController::class, [
    $container->get(EncounterRepositoryInterface::class),
    $container->get(LedgerRepositoryInterface::class),
    $container->get(ReceivablePaymentRepositoryInterface::class),
    $container->get(NumberSequenceInterface::class),
    $container->get(BillingService::class),
    $container->get(AuditLogger::class),
]);

// ---------------------------------------------------------------------
//  Routes
// ---------------------------------------------------------------------

$router = new Router();

$router->registerMiddleware('headers', new SecurityHeaders($config));
$router->registerMiddleware('locale', new SetLocale($container->get(\Aster\Infrastructure\Support\Translator::class), $config));
$router->registerMiddleware('csrf', new VerifyCsrf($container->get(Csrf::class), $logger));
$router->registerMiddleware('auth', new Authenticate(
    $container->get(AuthService::class),
    $session,
    $config,
    $container->get(AuditLogger::class),
));

// One Authorize instance per permission, named so the route table reads as
// an access-control list.
foreach ([
    'appointments.view', 'appointments.write', 'appointments.view_all',
    'payments.view', 'payments.verify',
    'doctors.view', 'doctors.write',
    'services.view', 'services.write',
    'packages.view', 'packages.write',
    'facilities.view', 'facilities.write',
    'articles.view', 'articles.write',
    'inquiries.view', 'inquiries.write',
    'users.view', 'users.write',
    'settings.view', 'settings.write',
    'payment_methods.write',
    'audit.view',
    // Phase II (migration 006_phase2_permissions.sql)
    'patients.view', 'patients.write',
    'encounters.view', 'encounters.write',
    'billing.view', 'billing.write', 'billing.discharge', 'billing.override',
] as $permission) {
    $router->registerMiddleware('can:' . $permission, new Authorize($permission, $logger));
}

$publicStack = ['headers', 'locale'];
$publicForm  = ['headers', 'locale', 'csrf'];
$adminStack  = ['headers', 'locale', 'auth'];
$adminForm   = ['headers', 'locale', 'csrf', 'auth'];

// --- Public site ------------------------------------------------------

$router->get('/', [$home, 'index'], $publicStack, 'home');
$router->get('/privacy', [$home, 'privacy'], $publicStack, 'privacy');
$router->get('/terms', [$home, 'terms'], $publicStack, 'terms');

$router->get('/services', [$content, 'services'], $publicStack, 'services');
$router->get('/services/{slug}', [$content, 'serviceDetail'], $publicStack, 'service.detail');
$router->get('/doctors', [$content, 'doctors'], $publicStack, 'doctors');
$router->get('/doctors/{slug}', [$content, 'doctorDetail'], $publicStack, 'doctor.detail');
$router->get('/packages', [$content, 'packages'], $publicStack, 'packages');
$router->get('/health', [$content, 'articles'], $publicStack, 'articles');
$router->get('/health/{slug}', [$content, 'articleDetail'], $publicStack, 'article.detail');
$router->get('/locations', [$content, 'locations'], $publicStack, 'locations');

$router->get('/contact', [$contact, 'form'], $publicStack, 'contact');
$router->post('/contact', [$contact, 'store'], $publicForm);

// --- Booking ----------------------------------------------------------

$router->get('/book', [$booking, 'form'], $publicStack, 'book');
$router->post('/book', [$booking, 'store'], $publicForm);
$router->get('/api/availability', [$booking, 'availability'], $publicStack);
$router->get('/api/quote', [$booking, 'quote'], $publicStack);

$router->get('/my-booking', [$booking, 'lookupForm'], $publicStack, 'lookup');
$router->post('/my-booking', [$booking, 'lookup'], $publicForm);

$router->get('/booking/{reference}', [$booking, 'show'], $publicStack, 'booking.show');
$router->get('/booking/{reference}/pay', [$booking, 'paymentPage'], $publicStack, 'booking.pay');
$router->post('/booking/{reference}/pay', [$booking, 'submitProof'], $publicForm);
$router->post('/booking/{reference}/cancel', [$booking, 'cancel'], $publicForm);

// --- SEO & media ------------------------------------------------------

$router->get('/sitemap.xml', [$sitemap, 'sitemap'], ['headers']);
$router->get('/robots.txt', [$sitemap, 'robots'], ['headers']);
$router->get('/media/{path:.+}', [$sitemap, 'media'], ['headers']);

// --- Admin portal -----------------------------------------------------

$adminPath = $config->adminPath;

$router->group($adminPath, [], static function (Router $r) use (
    $auth, $dashboard, $appointments, $payments, $doctorsAdmin, $catalog,
    $articlesAdmin, $inquiriesAdmin, $usersAdmin, $settingsAdmin,
    $patientsAdmin, $encountersAdmin, $billingAdmin,
    $publicStack, $publicForm, $adminStack, $adminForm
): void {
    // Authentication (no auth middleware, obviously)
    $r->get('/login', [$auth, 'loginForm'], $publicStack, 'admin.login');
    $r->post('/login', [$auth, 'login'], $publicForm);
    $r->post('/logout', [$auth, 'logout'], $adminForm);

    $r->get('/password', [$auth, 'passwordForm'], $adminStack);
    $r->post('/password', [$auth, 'updatePassword'], $adminForm);

    $r->get('/', [$dashboard, 'index'], $adminStack, 'admin.home');
    $r->get('/dashboard', [$dashboard, 'index'], $adminStack);

    // Appointments
    $r->get('/appointments', [$appointments, 'index'], [...$adminStack, 'can:appointments.view']);
    $r->get('/appointments/day', [$appointments, 'daySheet'], [...$adminStack, 'can:appointments.view']);
    $r->get('/appointments/create', [$appointments, 'createForm'], [...$adminStack, 'can:appointments.write']);
    $r->post('/appointments', [$appointments, 'store'], [...$adminForm, 'can:appointments.write']);
    $r->get('/appointments/{id:\d+}', [$appointments, 'show'], [...$adminStack, 'can:appointments.view']);
    $r->post('/appointments/{id:\d+}/status', [$appointments, 'updateStatus'], [...$adminForm, 'can:appointments.write']);
    $r->post('/appointments/{id:\d+}/notes', [$appointments, 'updateNotes'], [...$adminForm, 'can:appointments.view']);
    $r->post('/appointments/{id:\d+}/check-in', [$appointments, 'checkIn'], [...$adminForm, 'can:appointments.write']);

    // Payments
    $r->get('/payments', [$payments, 'index'], [...$adminStack, 'can:payments.view']);
    $r->get('/payments/methods', [$payments, 'methods'], [...$adminStack, 'can:payments.view']);
    $r->post('/payments/methods', [$payments, 'storeMethod'], [...$adminForm, 'can:payment_methods.write']);
    $r->post('/payments/methods/{id:\d+}/delete', [$payments, 'deleteMethod'], [...$adminForm, 'can:payment_methods.write']);
    $r->get('/payments/{id:\d+}', [$payments, 'show'], [...$adminStack, 'can:payments.view']);
    $r->get('/payments/{id:\d+}/proof', [$payments, 'proof'], [...$adminStack, 'can:payments.view']);
    $r->post('/payments/{id:\d+}/verify', [$payments, 'verify'], [...$adminForm, 'can:payments.verify']);
    $r->post('/payments/{id:\d+}/reject', [$payments, 'reject'], [...$adminForm, 'can:payments.verify']);

    // Doctors
    $r->get('/doctors', [$doctorsAdmin, 'index'], [...$adminStack, 'can:doctors.view']);
    $r->get('/doctors/create', [$doctorsAdmin, 'form'], [...$adminStack, 'can:doctors.write']);
    $r->post('/doctors', [$doctorsAdmin, 'save'], [...$adminForm, 'can:doctors.write']);
    $r->get('/doctors/{id:\d+}/edit', [$doctorsAdmin, 'form'], [...$adminStack, 'can:doctors.write']);
    $r->post('/doctors/{id:\d+}', [$doctorsAdmin, 'save'], [...$adminForm, 'can:doctors.write']);
    $r->post('/doctors/{id:\d+}/delete', [$doctorsAdmin, 'delete'], [...$adminForm, 'can:doctors.write']);
    $r->post('/doctors/{id:\d+}/leave', [$doctorsAdmin, 'addTimeOff'], [...$adminForm, 'can:doctors.write']);
    $r->post('/doctors/{id:\d+}/leave/{leaveId:\d+}/delete', [$doctorsAdmin, 'removeTimeOff'], [...$adminForm, 'can:doctors.write']);

    // Services
    $r->get('/services', [$catalog, 'services'], [...$adminStack, 'can:services.view']);
    $r->get('/services/create', [$catalog, 'serviceForm'], [...$adminStack, 'can:services.write']);
    $r->post('/services', [$catalog, 'saveService'], [...$adminForm, 'can:services.write']);
    $r->get('/services/{id:\d+}/edit', [$catalog, 'serviceForm'], [...$adminStack, 'can:services.write']);
    $r->post('/services/{id:\d+}', [$catalog, 'saveService'], [...$adminForm, 'can:services.write']);
    $r->post('/services/{id:\d+}/delete', [$catalog, 'deleteService'], [...$adminForm, 'can:services.write']);

    // Packages
    $r->get('/packages', [$catalog, 'packages'], [...$adminStack, 'can:packages.view']);
    $r->get('/packages/create', [$catalog, 'packageForm'], [...$adminStack, 'can:packages.write']);
    $r->post('/packages', [$catalog, 'savePackage'], [...$adminForm, 'can:packages.write']);
    $r->get('/packages/{id:\d+}/edit', [$catalog, 'packageForm'], [...$adminStack, 'can:packages.write']);
    $r->post('/packages/{id:\d+}', [$catalog, 'savePackage'], [...$adminForm, 'can:packages.write']);
    $r->post('/packages/{id:\d+}/delete', [$catalog, 'deletePackage'], [...$adminForm, 'can:packages.write']);

    // Facilities
    $r->get('/facilities', [$catalog, 'facilities'], [...$adminStack, 'can:facilities.view']);
    $r->get('/facilities/create', [$catalog, 'facilityForm'], [...$adminStack, 'can:facilities.write']);
    $r->post('/facilities', [$catalog, 'saveFacility'], [...$adminForm, 'can:facilities.write']);
    $r->get('/facilities/{id:\d+}/edit', [$catalog, 'facilityForm'], [...$adminStack, 'can:facilities.write']);
    $r->post('/facilities/{id:\d+}', [$catalog, 'saveFacility'], [...$adminForm, 'can:facilities.write']);
    $r->post('/facilities/{id:\d+}/delete', [$catalog, 'deleteFacility'], [...$adminForm, 'can:facilities.write']);

    // Articles
    $r->get('/articles', [$articlesAdmin, 'index'], [...$adminStack, 'can:articles.view']);
    $r->get('/articles/create', [$articlesAdmin, 'form'], [...$adminStack, 'can:articles.write']);
    $r->post('/articles', [$articlesAdmin, 'save'], [...$adminForm, 'can:articles.write']);
    $r->get('/articles/{id:\d+}/edit', [$articlesAdmin, 'form'], [...$adminStack, 'can:articles.write']);
    $r->post('/articles/{id:\d+}', [$articlesAdmin, 'save'], [...$adminForm, 'can:articles.write']);
    $r->post('/articles/{id:\d+}/status', [$articlesAdmin, 'updateStatus'], [...$adminForm, 'can:articles.write']);
    $r->post('/articles/{id:\d+}/delete', [$articlesAdmin, 'delete'], [...$adminForm, 'can:articles.write']);

    // Enquiries
    $r->get('/inquiries', [$inquiriesAdmin, 'index'], [...$adminStack, 'can:inquiries.view']);
    $r->get('/inquiries/{id:\d+}', [$inquiriesAdmin, 'show'], [...$adminStack, 'can:inquiries.view']);
    $r->post('/inquiries/{id:\d+}', [$inquiriesAdmin, 'update'], [...$adminForm, 'can:inquiries.write']);
    $r->post('/inquiries/{id:\d+}/delete', [$inquiriesAdmin, 'delete'], [...$adminForm, 'can:inquiries.write']);

    // Staff accounts
    $r->get('/users', [$usersAdmin, 'index'], [...$adminStack, 'can:users.view']);
    $r->get('/users/create', [$usersAdmin, 'form'], [...$adminStack, 'can:users.write']);
    $r->post('/users', [$usersAdmin, 'save'], [...$adminForm, 'can:users.write']);
    $r->get('/users/{id:\d+}/edit', [$usersAdmin, 'form'], [...$adminStack, 'can:users.write']);
    $r->post('/users/{id:\d+}', [$usersAdmin, 'save'], [...$adminForm, 'can:users.write']);
    $r->post('/users/{id:\d+}/reset-password', [$usersAdmin, 'resetPassword'], [...$adminForm, 'can:users.write']);
    $r->post('/users/{id:\d+}/unlock', [$usersAdmin, 'unlock'], [...$adminForm, 'can:users.write']);
    $r->post('/users/{id:\d+}/delete', [$usersAdmin, 'delete'], [...$adminForm, 'can:users.write']);

    // Settings
    $r->get('/settings', [$settingsAdmin, 'index'], [...$adminStack, 'can:settings.view']);
    $r->post('/settings', [$settingsAdmin, 'save'], [...$adminForm, 'can:settings.write']);
    $r->get('/settings/audit', [$settingsAdmin, 'audit'], [...$adminStack, 'can:audit.view']);
    $r->get('/settings/mail', [$settingsAdmin, 'mail'], [...$adminStack, 'can:settings.view']);
    $r->post('/settings/mail/test', [$settingsAdmin, 'testMail'], [...$adminForm, 'can:settings.write']);
    $r->post('/settings/mail/{id:\d+}/retry', [$settingsAdmin, 'retryMail'], [...$adminForm, 'can:settings.write']);

    // Phase II - Master Patient Index
    $r->get('/patients', [$patientsAdmin, 'index'], [...$adminStack, 'can:patients.view']);
    $r->get('/patients/create', [$patientsAdmin, 'form'], [...$adminStack, 'can:patients.write']);
    $r->post('/patients', [$patientsAdmin, 'save'], [...$adminForm, 'can:patients.write']);
    $r->get('/patients/{id:\d+}', [$patientsAdmin, 'show'], [...$adminStack, 'can:patients.view']);

    // Phase II - Encounter workbench
    $r->get('/encounters/workbench', [$encountersAdmin, 'workbench'], [...$adminStack, 'can:encounters.view']);
    $r->post('/encounters/workbench', [$encountersAdmin, 'startWalkIn'], [...$adminForm, 'can:encounters.write']);
    $r->post('/encounters/upgrade-ipd', [$encountersAdmin, 'upgradeToIpd'], [...$adminForm, 'can:encounters.write']);
    $r->post('/encounters/{id:\d+}/discharge', [$encountersAdmin, 'discharge'], [...$adminForm, 'can:billing.discharge']);
    $r->post('/encounters/{id:\d+}/override', [$encountersAdmin, 'applyOverride'], [...$adminForm, 'can:billing.override']);
    $r->post('/encounters/{id:\d+}/walk-out', [$encountersAdmin, 'walkOut'], [...$adminForm, 'can:encounters.write']);

    // Phase II - Consumption ledger & payments
    $r->get('/billing/ledger', [$billingAdmin, 'ledger'], [...$adminStack, 'can:billing.view']);
    $r->post('/billing/ledger', [$billingAdmin, 'postLedgerEntry'], [...$adminForm, 'can:billing.write']);
    $r->post('/billing/payments', [$billingAdmin, 'postPayment'], [...$adminForm, 'can:billing.write']);
});

// ---------------------------------------------------------------------
//  Dispatch
// ---------------------------------------------------------------------

try {
    $response = $router->dispatch($request);
} catch (Throwable $e) {
    $response = $errorHandler->handle($e, $request);
}

$response->send();
