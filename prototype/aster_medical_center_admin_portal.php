<?php
declare(strict_types=1);

session_start();

/**
 * Aster Medical Center - Administration System
 * Built for PHP 8.3+
 */
class AdminConfig {
    public const string APP_NAME = 'Aster Medical Center Admin';
    public const string APP_VERSION = '2.4.0-PHP8.3';
    public const string DEFAULT_LOCATION = 'Bole Sub-city, Addis Ababa, Ethiopia';
    public const string TIMEZONE = 'Africa/Addis_Ababa';
}

date_default_timezone_set(AdminConfig::TIMEZONE);


enum AppointmentStatus: string {
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public function badgeClass(): string {
        return match($this) {
            self::PENDING => 'bg-amber-100 text-amber-800 border-amber-200',
            self::CONFIRMED => 'bg-teal-100 text-teal-800 border-teal-200',
            self::CANCELLED => 'bg-rose-100 text-rose-800 border-rose-200',
            self::COMPLETED => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        };
    }

    public function label(): string {
        return match($this) {
            self::PENDING => 'Pending Review',
            self::CONFIRMED => 'Confirmed',
            self::CANCELLED => 'Cancelled',
            self::COMPLETED => 'Completed',
        };
    }
}

enum FacilityStatus: string {
    case OPERATIONAL = 'operational';
    case MAINTENANCE = 'maintenance';
    case UPGRADING = 'upgrading';

    public function badgeClass(): string {
        return match($this) {
            self::OPERATIONAL => 'bg-emerald-100 text-emerald-800',
            self::MAINTENANCE => 'bg-amber-100 text-amber-800',
            self::UPGRADING => 'bg-blue-100 text-blue-800',
        };
    }
}

readonly class UserSession {
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $role,
        public string $avatar
    ) {}
}

if (!isset($_SESSION['aster_db'])) {
    $_SESSION['aster_db'] = [
        'doctors' => [
            ['id' => 'DOC-101', 'name' => 'Dr. Hana Tesfaye', 'department' => 'Internal Medicine', 'qualification' => 'MD, Internal Med', 'experience' => '12 Yrs', 'phone' => '+251 911 234 567', 'active' => true, 'avatar' => 'HT'],
            ['id' => 'DOC-102', 'name' => 'Dr. Dawit Bekele', 'department' => 'Cardiology', 'qualification' => 'MD, FACC', 'experience' => '10 Yrs', 'phone' => '+251 911 345 678', 'active' => true, 'avatar' => 'DB'],
            ['id' => 'DOC-103', 'name' => 'Dr. Sara Alemu', 'department' => 'Pediatrics', 'qualification' => 'MD, Pediatrics', 'experience' => '9 Yrs', 'phone' => '+251 911 456 789', 'active' => true, 'avatar' => 'SA'],
            ['id' => 'DOC-104', 'name' => 'Dr. Michael Getachew', 'department' => 'Orthopedics', 'qualification' => 'MD, Ortho Surgery', 'experience' => '11 Yrs', 'phone' => '+251 911 567 890', 'active' => false, 'avatar' => 'MG'],
        ],
        'services' => [
            ['id' => 'SRV-01', 'name' => 'Cardiology Care', 'category' => 'Clinical', 'icon' => '🫀', 'price' => 1200, 'active' => true, 'description' => 'Heart & vascular diagnosis and treatment.'],
            ['id' => 'SRV-02', 'name' => 'Pediatrics Consult', 'category' => 'Clinical', 'icon' => '🧒', 'price' => 800, 'active' => true, 'description' => 'Comprehensive child health services.'],
            ['id' => 'SRV-03', 'name' => 'Diagnostic Lab', 'category' => 'Diagnostics', 'icon' => '🧪', 'price' => 500, 'active' => true, 'description' => 'Full hematology & biochemistry panel.'],
            ['id' => 'SRV-04', 'name' => 'Ultrasound & X-Ray', 'category' => 'Imaging', 'icon' => '🩻', 'price' => 1500, 'active' => true, 'description' => 'Modern digital imaging diagnostics.'],
            ['id' => 'SRV-05', 'name' => 'Pharmacy Support', 'category' => 'Pharmacy', 'icon' => '💊', 'price' => 0, 'active' => true, 'description' => 'Prescription and medication counseling.'],
        ],
        'appointments' => [
            ['id' => 'APT-801', 'patient' => 'Meron Kebede', 'phone' => '+251 912 001 122', 'service' => 'Cardiology Care', 'doctor' => 'Dr. Dawit Bekele', 'date' => date('Y-m-d', strtotime('+1 day')), 'time' => '10:00–12:00', 'status' => 'pending', 'created_at' => date('Y-m-d H:i')],
            ['id' => 'APT-802', 'patient' => 'Abel Tadesse', 'phone' => '+251 912 334 455', 'service' => 'Pediatrics Consult', 'doctor' => 'Dr. Sara Alemu', 'date' => date('Y-m-d', strtotime('+2 days')), 'time' => '14:00–16:00', 'status' => 'confirmed', 'created_at' => date('Y-m-d H:i')],
            ['id' => 'APT-803', 'patient' => 'Selamawit Bekele', 'phone' => '+251 911 889 900', 'service' => 'Diagnostic Lab', 'doctor' => 'Dr. Hana Tesfaye', 'date' => date('Y-m-d'), 'time' => '08:00–10:00', 'status' => 'completed', 'created_at' => date('Y-m-d H:i', strtotime('-1 day'))],
            ['id' => 'APT-804', 'patient' => 'Yonas Girma', 'phone' => '+251 920 112 233', 'service' => 'Cardiology Care', 'doctor' => 'Dr. Dawit Bekele', 'date' => date('Y-m-d', strtotime('+3 days')), 'time' => '16:00–18:00', 'status' => 'pending', 'created_at' => date('Y-m-d H:i')],
        ],
        'facilities' => [
            ['id' => 'FAC-01', 'name' => 'Consultation Suite Alpha', 'type' => 'Clinical Room', 'room' => 'Room 102', 'status' => 'operational', 'notes' => 'Fully equipped for internal medicine.'],
            ['id' => 'FAC-02', 'name' => 'Diagnostic Imaging Wing', 'type' => 'Radiology', 'room' => 'Ground Floor B', 'status' => 'operational', 'notes' => 'X-ray calibration completed this week.'],
            ['id' => 'FAC-03', 'name' => 'Clinical Pathology Lab', 'type' => 'Laboratory', 'room' => 'Level 2', 'status' => 'maintenance', 'notes' => 'Centrifuge unit under scheduled inspection.'],
            ['id' => 'FAC-04', 'name' => 'Emergency Response Room', 'type' => 'Urgent Care', 'room' => 'Main Gate Suite', 'status' => 'operational', 'notes' => '24/7 triaging ready.'],
        ],
        'articles' => [
            ['id' => 'ART-01', 'title' => '5 Habits That Support Heart Health', 'category' => 'Prevention', 'author' => 'Dr. Dawit Bekele', 'status' => 'Published', 'views' => 482, 'date' => '2026-02-10'],
            ['id' => 'ART-02', 'title' => 'When Should Your Child See a Pediatrician?', 'category' => 'Family Health', 'author' => 'Dr. Sara Alemu', 'status' => 'Published', 'views' => 310, 'date' => '2026-02-18'],
            ['id' => 'ART-03', 'title' => 'Understanding Preventive Health Screening', 'category' => 'Wellness', 'author' => 'Dr. Hana Tesfaye', 'status' => 'Draft', 'views' => 0, 'date' => '2026-03-01'],
        ],
        'inquiries' => [
            ['id' => 'INQ-501', 'name' => 'Tewodros Kassahun', 'phone' => '+251 911 654 321', 'email' => 'teddy@example.com', 'message' => 'Do you provide corporate annual health checkup packages?', 'status' => 'unread', 'date' => date('Y-m-d H:i')],
            ['id' => 'INQ-502', 'name' => 'Bethlehem Worku', 'phone' => '+251 922 111 222', 'email' => 'bety@example.com', 'message' => 'What are the visiting hours for specialist consultations?', 'status' => 'responded', 'date' => date('Y-m-d H:i', strtotime('-2 days'))],
        ],
        'settings' => [
            'clinic_name' => 'Aster Medical Center',
            'city' => 'Addis Ababa',
            'country' => 'Ethiopia',
            'phone_primary' => '+251 911 123 456',
            'phone_emergency' => '+251 911 999 000',
            'email' => 'hello@astermedical.et',
            'operating_hours' => 'Mon–Sat: 08:00 – 18:00 (Emergency 24/7)',
            'ethiopian_calendar' => true,
            'default_lang' => 'en'
        ]
    ];
}

$flash_message = null;
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Validate JSON input payloads if applicable (PHP 8.3 json_validate demo)
    if (isset($_POST['json_payload']) && is_string($_POST['json_payload'])) {
        if (!json_validate($_POST['json_payload'])) {
            $flash_message = "Invalid JSON payload format submitted.";
            $flash_type = "error";
            $action = '';
        }
    }

    switch ($action) {
        case 'update_appointment_status':
            $apt_id = $_POST['appointment_id'] ?? '';
            $new_status = $_POST['new_status'] ?? '';
            foreach ($_SESSION['aster_db']['appointments'] as &$apt) {
                if ($apt['id'] === $apt_id) {
                    $apt['status'] = $new_status;
                    $flash_message = "Appointment {$apt_id} status updated to '{$new_status}'.";
                    break;
                }
            }
            break;

        case 'add_doctor':
            $name = trim($_POST['name'] ?? '');
            $dept = trim($_POST['department'] ?? '');
            $qual = trim($_POST['qualification'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            if ($name !== '' && $dept !== '') {
                $new_id = 'DOC-' . (count($_SESSION['aster_db']['doctors']) + 101);
                $initials = strtoupper(substr($name, 4, 1) . substr(explode(' ', $name)[1] ?? 'T', 0, 1));
                $_SESSION['aster_db']['doctors'][] = [
                    'id' => $new_id,
                    'name' => $name,
                    'department' => $dept,
                    'qualification' => $qual,
                    'experience' => '1 Yr',
                    'phone' => $phone,
                    'active' => true,
                    'avatar' => $initials
                ];
                $flash_message = "New specialist '{$name}' registered successfully.";
            }
            break;

        case 'toggle_doctor_status':
            $doc_id = $_POST['doctor_id'] ?? '';
            foreach ($_SESSION['aster_db']['doctors'] as &$doc) {
                if ($doc['id'] === $doc_id) {
                    $doc['active'] = !$doc['active'];
                    $status_str = $doc['active'] ? 'activated' : 'deactivated';
                    $flash_message = "Dr. {$doc['name']} has been {$status_str}.";
                    break;
                }
            }
            break;

        case 'add_service':
            $s_name = trim($_POST['name'] ?? '');
            $s_cat = $_POST['category'] ?? 'Clinical';
            $s_price = (float)($_POST['price'] ?? 0);
            $s_desc = trim($_POST['description'] ?? '');
            $s_icon = $_POST['icon'] ?? '🩺';
            if ($s_name !== '') {
                $s_id = 'SRV-0' . (count($_SESSION['aster_db']['services']) + 1);
                $_SESSION['aster_db']['services'][] = [
                    'id' => $s_id,
                    'name' => $s_name,
                    'category' => $s_cat,
                    'icon' => $s_icon,
                    'price' => $s_price,
                    'active' => true,
                    'description' => $s_desc
                ];
                $flash_message = "Service '{$s_name}' added to clinical offerings.";
            }
            break;

        case 'add_appointment':
            $p_name = trim($_POST['patient_name'] ?? '');
            $p_phone = trim($_POST['phone'] ?? '');
            $srv = $_POST['service'] ?? '';
            $doc = $_POST['doctor'] ?? 'Any Available Doctor';
            $date = $_POST['date'] ?? date('Y-m-d');
            $time = $_POST['time'] ?? '10:00–12:00';
            if ($p_name !== '') {
                $a_id = 'APT-' . rand(805, 999);
                $_SESSION['aster_db']['appointments'][] = [
                    'id' => $a_id,
                    'patient' => $p_name,
                    'phone' => $p_phone,
                    'service' => $srv,
                    'doctor' => $doc,
                    'date' => $date,
                    'time' => $time,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i')
                ];
                $flash_message = "Manual appointment logged for {$p_name} ({$a_id}).";
            }
            break;

        case 'add_article':
            $title = trim($_POST['title'] ?? '');
            $cat = $_POST['category'] ?? 'General';
            $author = $_POST['author'] ?? 'Medical Team';
            $status = $_POST['status'] ?? 'Draft';
            if ($title !== '') {
                $art_id = 'ART-0' . (count($_SESSION['aster_db']['articles']) + 1);
                $_SESSION['aster_db']['articles'][] = [
                    'id' => $art_id,
                    'title' => $title,
                    'category' => $cat,
                    'author' => $author,
                    'status' => $status,
                    'views' => 0,
                    'date' => date('Y-m-d')
                ];
                $flash_message = "Health article '{$title}' saved as {$status}.";
            }
            break;

        case 'toggle_inquiry_status':
            $inq_id = $_POST['inquiry_id'] ?? '';
            foreach ($_SESSION['aster_db']['inquiries'] as &$inq) {
                if ($inq['id'] === $inq_id) {
                    $inq['status'] = $inq['status'] === 'unread' ? 'responded' : 'unread';
                    $flash_message = "Inquiry {$inq_id} status toggled.";
                    break;
                }
            }
            break;

        case 'save_settings':
            $_SESSION['aster_db']['settings']['clinic_name'] = trim($_POST['clinic_name'] ?? 'Aster Medical Center');
            $_SESSION['aster_db']['settings']['phone_primary'] = trim($_POST['phone_primary'] ?? '');
            $_SESSION['aster_db']['settings']['phone_emergency'] = trim($_POST['phone_emergency'] ?? '');
            $_SESSION['aster_db']['settings']['email'] = trim($_POST['email'] ?? '');
            $_SESSION['aster_db']['settings']['operating_hours'] = trim($_POST['operating_hours'] ?? '');
            $_SESSION['aster_db']['settings']['ethiopian_calendar'] = isset($_POST['ethiopian_calendar']);
            $flash_message = "Clinic settings and localization configurations saved.";
            break;
    }
}

// Active tab determination
$current_tab = $_GET['tab'] ?? 'dashboard';

// Compute Overview Metrics
$total_appointments = count($_SESSION['aster_db']['appointments']);
$pending_appointments = count(array_filter($_SESSION['aster_db']['appointments'], fn($a) => $a['status'] === 'pending'));
$active_doctors = count(array_filter($_SESSION['aster_db']['doctors'], fn($d) => $d['active']));
$active_services = count(array_filter($_SESSION['aster_db']['services'], fn($s) => $s['active']));
$unread_inquiries = count(array_filter($_SESSION['aster_db']['inquiries'], fn($i) => $i['status'] === 'unread'));

$currentUser = new UserSession(
    id: 'ADM-001',
    name: 'Dr. Bethlehem Tadesse',
    email: 'admin@astermedical.et',
    role: 'Medical Director',
    avatar: 'BT'
);

?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(AdminConfig::APP_NAME) ?> - Control Panel</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        medical: {
                            50: '#effcfb',
                            100: '#d8f7f4',
                            500: '#0f8f89',
                            600: '#087b77',
                            700: '#056460',
                            800: '#0c4a47',
                            900: '#063b3a',
                            950: '#032423'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .tab-active {
            background-color: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            font-weight: 700;
            border-left: 4px solid #9ce6e1;
        }
    </style>
</head>
<body class="h-full font-sans antialiased text-slate-800">

<div class="min-h-full flex flex-col lg:flex-row">

    <aside class="w-full lg:w-72 bg-medical-950 text-white flex-shrink-0 flex flex-col justify-between">
        <div>
            <!-- Brand Banner -->
            <div class="p-6 border-b border-white/10 flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-medical-600 flex items-center justify-center font-extrabold text-white text-xl shadow-md">
                    A
                </div>
                <div>
                    <h1 class="font-extrabold text-base tracking-tight leading-none text-white"><?= htmlspecialchars($_SESSION['aster_db']['settings']['clinic_name']) ?></h1>
                    <span class="text-[10px] uppercase font-bold tracking-widest text-teal-300">ADMIN CONTROL CENTER</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1.5 text-sm font-medium">
                <a href="?tab=dashboard" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition <?= $current_tab === 'dashboard' ? 'tab-active' : 'text-white/70' ?>">
                    <span>📊</span> <span>Dashboard Overview</span>
                </a>
                <a href="?tab=appointments" class="flex items-center justify-between px-4 py-3 rounded-xl hover:bg-white/5 transition <?= $current_tab === 'appointments' ? 'tab-active' : 'text-white/70' ?>">
                    <div class="flex items-center gap-3">
                        <span>📅</span> <span>Appointments</span>
                    </div>
                    <?php if ($pending_appointments > 0): ?>
                        <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-500 text-slate-950"><?= $pending_appointments ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=doctors" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition <?= $current_tab === 'doctors' ? 'tab-active' : 'text-white/70' ?>">
                    <span>👨‍⚕️</span> <span>Doctors Directory</span>
                </a>
                <a href="?tab=services" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition <?= $current_tab === 'services' ? 'tab-active' : 'text-white/70' ?>">
                    <span>🩺</span> <span>Medical Services</span>
                </a>
                <a href="?tab=facilities" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition <?= $current_tab === 'facilities' ? 'tab-active' : 'text-white/70' ?>">
                    <span>🏥</span> <span>Facilities & Rooms</span>
                </a>
                <a href="?tab=articles" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition <?= $current_tab === 'articles' ? 'tab-active' : 'text-white/70' ?>">
                    <span>📰</span> <span>Health Articles CMS</span>
                </a>
                <a href="?tab=inquiries" class="flex items-center justify-between px-4 py-3 rounded-xl hover:bg-white/5 transition <?= $current_tab === 'inquiries' ? 'tab-active' : 'text-white/70' ?>">
                    <div class="flex items-center gap-3">
                        <span>💬</span> <span>Patient Inquiries</span>
                    </div>
                    <?php if ($unread_inquiries > 0): ?>
                        <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-teal-400 text-slate-950"><?= $unread_inquiries ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=settings" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition <?= $current_tab === 'settings' ? 'tab-active' : 'text-white/70' ?>">
                    <span>⚙️</span> <span>Clinic Settings</span>
                </a>
            </nav>
        </div>

        <!-- Sidebar Footer & Logged In User -->
        <div class="p-4 border-t border-white/10 bg-black/20">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-full bg-teal-500 text-medical-950 font-bold flex items-center justify-center text-sm">
                    <?= $currentUser->avatar ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($currentUser->name) ?></p>
                    <p class="text-[10px] text-teal-300 font-medium truncate"><?= htmlspecialchars($currentUser->role) ?></p>
                </div>
            </div>
            <div class="mt-3 text-[10px] text-white/40 font-mono text-center">
                <?= AdminConfig::APP_VERSION ?> · Addis Ababa
            </div>
        </div>
    </aside>

    <!-- Main Content Body -->
    <main class="flex-1 flex flex-col min-w-0 bg-slate-100 overflow-x-hidden">

        <!-- Top Header Bar -->
        <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-extrabold text-medical-950 capitalize">
                    <?= str_replace('_', ' ', $current_tab) ?>
                </h2>
                <p class="text-xs text-slate-500 font-medium">
                    Aster Medical Center · System Status: <span class="text-emerald-600 font-bold">Online (PHP 8.3)</span>
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                <!-- System Time Display -->
                <div class="hidden sm:block text-right pr-3 border-r border-slate-200">
                    <span class="block text-xs font-bold text-slate-700"><?= date('D, d M Y') ?></span>
                    <span class="block text-[11px] text-slate-400 font-mono"><?= date('H:i T') ?></span>
                </div>
                <!-- Quick Action Modal Button -->
                <button onclick="document.getElementById('quick-apt-modal').classList.remove('hidden')" class="px-4 py-2 bg-medical-700 hover:bg-medical-600 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                    <span>➕</span> <span>New Appointment</span>
                </button>
            </div>
        </header>

        <!-- Flash Alert Message -->
        <?php if ($flash_message): ?>
            <div class="mx-6 mt-6 p-4 rounded-xl text-sm font-semibold flex items-center justify-between shadow-sm <?= $flash_type === 'error' ? 'bg-rose-50 border border-rose-200 text-rose-800' : 'bg-teal-50 border border-teal-200 text-teal-900' ?>">
                <div class="flex items-center gap-2">
                    <span><?= $flash_type === 'error' ? '⚠️' : '✅' ?></span>
                    <span><?= htmlspecialchars($flash_message) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
        <?php endif; ?>

        <!-- Content Body Area -->
        <div class="p-6 space-y-6">

            <?php
            if ($current_tab === 'dashboard'):
            ?>
                <!-- Metric Cards Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Appointments</span>
                            <span class="text-xl">📅</span>
                        </div>
                        <strong class="text-3xl font-extrabold text-medical-900 mt-2 block"><?= $total_appointments ?></strong>
                        <span class="text-xs text-amber-600 font-semibold mt-1 block"><?= $pending_appointments ?> pending confirmation</span>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Specialists</span>
                            <span class="text-xl">👨‍⚕️</span>
                        </div>
                        <strong class="text-3xl font-extrabold text-medical-900 mt-2 block"><?= $active_doctors ?></strong>
                        <span class="text-xs text-slate-500 font-medium mt-1 block">Across 4 departments</span>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Clinical Services</span>
                            <span class="text-xl">🩺</span>
                        </div>
                        <strong class="text-3xl font-extrabold text-medical-900 mt-2 block"><?= $active_services ?></strong>
                        <span class="text-xs text-emerald-600 font-semibold mt-1 block">100% operational</span>
                    </div>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Patient Inquiries</span>
                            <span class="text-xl">💬</span>
                        </div>
                        <strong class="text-3xl font-extrabold text-medical-900 mt-2 block"><?= count($_SESSION['aster_db']['inquiries']) ?></strong>
                        <span class="text-xs text-teal-700 font-semibold mt-1 block"><?= $unread_inquiries ?> unread messages</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Recent Appointments List -->
                    <div class="lg:col-span-2 bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-extrabold text-medical-900 text-base">Recent Appointment Requests</h3>
                            <a href="?tab=appointments" class="text-xs font-bold text-medical-600 hover:underline">View All →</a>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <?php foreach (array_slice($_SESSION['aster_db']['appointments'], 0, 4) as $apt): 
                                $statusEnum = AppointmentStatus::from($apt['status']);
                            ?>
                                <div class="py-3.5 flex items-center justify-between gap-4">
                                    <div>
                                        <b class="text-sm text-slate-900 block"><?= htmlspecialchars($apt['patient']) ?></b>
                                        <span class="text-xs text-slate-500"><?= htmlspecialchars($apt['service']) ?> · <?= htmlspecialchars($apt['date']) ?> (<?= $apt['time'] ?>)</span>
                                    </div>
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-full border <?= $statusEnum->badgeClass() ?>">
                                        <?= $statusEnum->label() ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Facility Quick Status -->
                    <div class="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-extrabold text-medical-900 text-base">Facility Readiness</h3>
                            <a href="?tab=facilities" class="text-xs font-bold text-medical-600 hover:underline">Manage</a>
                        </div>
                        <div class="space-y-3">
                            <?php foreach ($_SESSION['aster_db']['facilities'] as $fac): 
                                $fStatus = FacilityStatus::from($fac['status']);
                            ?>
                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between">
                                    <div>
                                        <b class="text-xs font-bold text-slate-800 block"><?= htmlspecialchars($fac['name']) ?></b>
                                        <span class="text-[11px] text-slate-400"><?= htmlspecialchars($fac['room']) ?></span>
                                    </div>
                                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-md uppercase <?= $fStatus->badgeClass() ?>">
                                        <?= $fac['status'] ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            <?php
            elseif ($current_tab === 'appointments'):
            ?>
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between gap-4 flex-wrap">
                        <div>
                            <h3 class="font-extrabold text-medical-900 text-lg">Appointment Requests & Bookings</h3>
                            <p class="text-xs text-slate-500">Manage intake schedules and confirm patient bookings in Addis Ababa.</p>
                        </div>
                        <button onclick="document.getElementById('quick-apt-modal').classList.remove('hidden')" class="px-4 py-2 bg-medical-700 hover:bg-medical-600 text-white rounded-xl text-xs font-bold">
                            + Book Patient
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-400 font-bold text-[11px] uppercase tracking-wider border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-3.5">ID</th>
                                    <th class="px-6 py-3.5">Patient Details</th>
                                    <th class="px-6 py-3.5">Requested Service</th>
                                    <th class="px-6 py-3.5">Assigned Specialist</th>
                                    <th class="px-6 py-3.5">Schedule</th>
                                    <th class="px-6 py-3.5">Status</th>
                                    <th class="px-6 py-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <?php foreach ($_SESSION['aster_db']['appointments'] as $apt): 
                                    $st = AppointmentStatus::from($apt['status']);
                                ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="px-6 py-4 font-mono text-xs font-bold text-slate-400"><?= $apt['id'] ?></td>
                                        <td class="px-6 py-4">
                                            <b class="text-slate-900 block"><?= htmlspecialchars($apt['patient']) ?></b>
                                            <span class="text-xs text-slate-400"><?= htmlspecialchars($apt['phone']) ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-700"><?= htmlspecialchars($apt['service']) ?></td>
                                        <td class="px-6 py-4 text-slate-700"><?= htmlspecialchars($apt['doctor']) ?></td>
                                        <td class="px-6 py-4">
                                            <span class="block text-slate-900 font-semibold text-xs"><?= htmlspecialchars($apt['date']) ?></span>
                                            <span class="text-[11px] text-slate-400"><?= htmlspecialchars($apt['time']) ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2.5 py-1 text-xs font-bold rounded-full border <?= $st->badgeClass() ?>">
                                                <?= $st->label() ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <form method="POST" class="inline-flex gap-1">
                                                <input type="hidden" name="action" value="update_appointment_status">
                                                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                <?php if ($apt['status'] !== 'confirmed'): ?>
                                                    <button name="new_status" value="confirmed" title="Confirm" class="px-2.5 py-1 bg-teal-50 hover:bg-teal-100 text-teal-800 rounded-lg text-xs font-bold">
                                                        Confirm
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($apt['status'] !== 'completed'): ?>
                                                    <button name="new_status" value="completed" title="Complete" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 rounded-lg text-xs font-bold">
                                                        Done
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($apt['status'] !== 'cancelled'): ?>
                                                    <button name="new_status" value="cancelled" title="Cancel" class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-800 rounded-lg text-xs font-bold">
                                                        Cancel
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php
            elseif ($current_tab === 'doctors'):
            ?>
                <div class="space-y-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-extrabold text-medical-900 text-xl">Specialist Physicians Directory</h3>
                            <p class="text-xs text-slate-500">Manage clinical staff, credentials, and active consultation availability.</p>
                        </div>
                        <button onclick="document.getElementById('add-doctor-modal').classList.remove('hidden')" class="px-4 py-2.5 bg-medical-700 hover:bg-medical-600 text-white rounded-xl text-xs font-bold shadow-sm">
                            + Add New Doctor
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                        <?php foreach ($_SESSION['aster_db']['doctors'] as $doc): ?>
                            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="h-12 w-12 rounded-2xl bg-teal-50 text-teal-700 border border-teal-100 font-extrabold text-lg flex items-center justify-center">
                                            <?= htmlspecialchars($doc['avatar']) ?>
                                        </div>
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full <?= $doc['active'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' ?>">
                                            <?= $doc['active'] ? 'Active Staff' : 'Inactive' ?>
                                        </span>
                                    </div>
                                    <h4 class="font-extrabold text-slate-900 text-base"><?= htmlspecialchars($doc['name']) ?></h4>
                                    <p class="text-xs font-bold text-medical-600 mt-0.5"><?= htmlspecialchars($doc['department']) ?></p>
                                    <p class="text-xs text-slate-400 mt-2"><?= htmlspecialchars($doc['qualification']) ?> (<?= $doc['experience'] ?>)</p>
                                    <p class="text-xs text-slate-500 font-mono mt-1"><?= htmlspecialchars($doc['phone']) ?></p>
                                </div>

                                <div class="mt-5 pt-4 border-t border-slate-100">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_doctor_status">
                                        <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">
                                        <button class="w-full py-2 rounded-xl text-xs font-bold border transition <?= $doc['active'] ? 'border-rose-200 text-rose-700 hover:bg-rose-50' : 'border-teal-200 text-teal-700 hover:bg-teal-50' ?>">
                                            <?= $doc['active'] ? 'Deactivate Consultation' : 'Activate Specialist' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php
            elseif ($current_tab === 'services'):
            ?>
                <div class="space-y-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-extrabold text-medical-900 text-xl">Medical Services Catalog</h3>
                            <p class="text-xs text-slate-500">Configure offerings, diagnostic descriptions, and consultation rates (ETB).</p>
                        </div>
                        <button onclick="document.getElementById('add-service-modal').classList.remove('hidden')" class="px-4 py-2.5 bg-medical-700 hover:bg-medical-600 text-white rounded-xl text-xs font-bold shadow-sm">
                            + Add Service
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <?php foreach ($_SESSION['aster_db']['services'] as $srv): ?>
                            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm">
                                <div class="text-3xl mb-3"><?= $srv['icon'] ?></div>
                                <h4 class="font-extrabold text-slate-900 text-lg"><?= htmlspecialchars($srv['name']) ?></h4>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded bg-teal-50 text-teal-700 border border-teal-100 mt-1 inline-block">
                                    <?= htmlspecialchars($srv['category']) ?>
                                </span>
                                <p class="text-xs text-slate-500 mt-3 leading-relaxed"><?= htmlspecialchars($srv['description']) ?></p>
                                <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
                                    <span class="text-xs text-slate-400 font-medium">Standard Fee:</span>
                                    <strong class="text-sm font-extrabold text-medical-900">
                                        <?= $srv['price'] > 0 ? 'ETB ' . number_format($srv['price']) : 'Included' ?>
                                    </strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php
            elseif ($current_tab === 'facilities'):
            ?>
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-5">
                    <div>
                        <h3 class="font-extrabold text-medical-900 text-xl">Facility Infrastructure & Rooms</h3>
                        <p class="text-xs text-slate-500">Operational readiness of consultation suites, imaging, and laboratory labs.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($_SESSION['aster_db']['facilities'] as $fac): 
                            $fStatus = FacilityStatus::from($fac['status']);
                        ?>
                            <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <b class="text-base text-slate-900 font-extrabold"><?= htmlspecialchars($fac['name']) ?></b>
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-md uppercase <?= $fStatus->badgeClass() ?>">
                                            <?= $fac['status'] ?>
                                        </span>
                                    </div>
                                    <p class="text-xs font-bold text-medical-600 mt-1"><?= htmlspecialchars($fac['type']) ?> · <?= htmlspecialchars($fac['room']) ?></p>
                                    <p class="text-xs text-slate-500 mt-2 bg-white p-2.5 rounded-lg border border-slate-100">
                                        📌 <?= htmlspecialchars($fac['notes']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php
            elseif ($current_tab === 'articles'):
            ?>
                <div class="space-y-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-extrabold text-medical-900 text-xl">Patient Health Knowledge Base</h3>
                            <p class="text-xs text-slate-500">Publish preventive medical advice and wellness articles for website visitors.</p>
                        </div>
                        <button onclick="document.getElementById('add-article-modal').classList.remove('hidden')" class="px-4 py-2.5 bg-medical-700 hover:bg-medical-600 text-white rounded-xl text-xs font-bold shadow-sm">
                            + Create Article
                        </button>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-400 font-bold text-[11px] uppercase tracking-wider border-b border-slate-100">
                                <tr>
                                    <th class="px-6 py-3.5">Article Title</th>
                                    <th class="px-6 py-3.5">Category</th>
                                    <th class="px-6 py-3.5">Author</th>
                                    <th class="px-6 py-3.5">Views</th>
                                    <th class="px-6 py-3.5">Status</th>
                                    <th class="px-6 py-3.5">Published Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <?php foreach ($_SESSION['aster_db']['articles'] as $art): ?>
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-6 py-4 font-bold text-slate-900"><?= htmlspecialchars($art['title']) ?></td>
                                        <td class="px-6 py-4 text-xs font-bold text-medical-600"><?= htmlspecialchars($art['category']) ?></td>
                                        <td class="px-6 py-4 text-slate-600 text-xs"><?= htmlspecialchars($art['author']) ?></td>
                                        <td class="px-6 py-4 font-mono text-xs"><?= number_format($art['views']) ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2.5 py-1 text-xs font-bold rounded-full <?= $art['status'] === 'Published' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' ?>">
                                                <?= $art['status'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-xs text-slate-400"><?= htmlspecialchars($art['date']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php
            elseif ($current_tab === 'inquiries'):
            ?>
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <div>
                        <h3 class="font-extrabold text-medical-900 text-xl">Patient Messages & Web Inquiries</h3>
                        <p class="text-xs text-slate-500">Inbound inquiries submitted through the public website contact form.</p>
                    </div>

                    <div class="space-y-3">
                        <?php foreach ($_SESSION['aster_db']['inquiries'] as $inq): ?>
                            <div class="p-4 rounded-xl border <?= $inq['status'] === 'unread' ? 'border-teal-300 bg-teal-50/30' : 'border-slate-200 bg-white' ?> flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <b class="text-sm font-extrabold text-slate-900"><?= htmlspecialchars($inq['name']) ?></b>
                                        <span class="text-xs text-slate-400 font-mono"><?= htmlspecialchars($inq['phone']) ?> · <?= htmlspecialchars($inq['email']) ?></span>
                                    </div>
                                    <p class="text-xs text-slate-700 mt-2 leading-relaxed">"<?= htmlspecialchars($inq['message']) ?>"</p>
                                    <span class="text-[10px] text-slate-400 block mt-2"><?= $inq['date'] ?></span>
                                </div>
                                <div>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_inquiry_status">
                                        <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
                                        <button class="px-3 py-1.5 text-xs font-bold rounded-xl border transition <?= $inq['status'] === 'unread' ? 'bg-teal-700 text-white border-teal-700 hover:bg-teal-600' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200' ?>">
                                            <?= $inq['status'] === 'unread' ? 'Mark Responded' : 'Reopen Inquiry' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php
            elseif ($current_tab === 'settings'):
            ?>
                <form method="POST" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-6 max-w-3xl">
                    <input type="hidden" name="action" value="save_settings">

                    <div>
                        <h3 class="font-extrabold text-medical-900 text-xl">Clinic Settings & Regional Localization</h3>
                        <p class="text-xs text-slate-500">Configure core clinic information, emergency lines, and localized features.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="block space-y-1">
                            <span class="text-xs font-bold text-slate-700">Clinic Name</span>
                            <input name="clinic_name" value="<?= htmlspecialchars($_SESSION['aster_db']['settings']['clinic_name']) ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:border-medical-500 outline-none">
                        </label>

                        <label class="block space-y-1">
                            <span class="text-xs font-bold text-slate-700">Primary Phone</span>
                            <input name="phone_primary" value="<?= htmlspecialchars($_SESSION['aster_db']['settings']['phone_primary']) ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:border-medical-500 outline-none">
                        </label>

                        <label class="block space-y-1">
                            <span class="text-xs font-bold text-slate-700">24/7 Emergency Line</span>
                            <input name="phone_emergency" value="<?= htmlspecialchars($_SESSION['aster_db']['settings']['phone_emergency']) ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:border-medical-500 outline-none">
                        </label>

                        <label class="block space-y-1">
                            <span class="text-xs font-bold text-slate-700">Official Contact Email</span>
                            <input name="email" type="email" value="<?= htmlspecialchars($_SESSION['aster_db']['settings']['email']) ?>" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:border-medical-500 outline-none">
                        </label>

                        <label class="block sm:col-span-2 space-y-1">
                            <span class="text-xs font-bold text-slate-700">Operating Hours Description</span>
                            <input name="operating_hours" value="<?= htmlspecialchars($_SESSION['aster_db']['settings']['operating_hours']) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm focus:border-medical-500 outline-none">
                        </label>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
                        <input type="checkbox" id="ethiopian_calendar" name="ethiopian_calendar" <?= $_SESSION['aster_db']['settings']['ethiopian_calendar'] ? 'checked' : '' ?> class="h-4 w-4 text-medical-700 rounded border-slate-300">
                        <label for="ethiopian_calendar" class="text-xs font-bold text-slate-700 cursor-pointer">
                            Enable Ethiopian Calendar (ዓመተ ምሕረት) Sync Support in Intake Reports
                        </label>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex justify-end">
                        <button class="px-6 py-3 bg-medical-700 hover:bg-medical-600 text-white font-bold text-xs rounded-xl shadow-sm transition">
                            Save Clinic Settings
                        </button>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </main>
</div>


<!-- Quick Appointment Modal -->
<div id="quick-apt-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
        <button onclick="document.getElementById('quick-apt-modal').classList.add('hidden')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">✕</button>
        <h3 class="font-extrabold text-medical-900 text-lg mb-4">Book Patient Appointment</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_appointment">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Patient Full Name *</label>
                <input name="patient_name" required placeholder="e.g. Meron Kebede" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Phone Number *</label>
                <input name="phone" required placeholder="+251 9XX XXX XXX" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Service *</label>
                <select name="service" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
                    <?php foreach ($_SESSION['aster_db']['services'] as $s): ?>
                        <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Date</label>
                    <input name="date" type="date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Time Block</label>
                    <select name="time" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
                        <option>08:00–10:00</option>
                        <option selected>10:00–12:00</option>
                        <option>14:00–16:00</option>
                    </select>
                </div>
            </div>
            <button class="w-full py-3 bg-medical-700 hover:bg-medical-600 text-white font-bold text-xs rounded-xl mt-2">
                Save Booking
            </button>
        </form>
    </div>
</div>

<!-- Add Doctor Modal -->
<div id="add-doctor-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
        <button onclick="document.getElementById('add-doctor-modal').classList.add('hidden')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">✕</button>
        <h3 class="font-extrabold text-medical-900 text-lg mb-4">Register New Specialist</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_doctor">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Doctor Name (with Title) *</label>
                <input name="name" placeholder="Dr. Abebe Bekele" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Department Specialty *</label>
                <input name="department" placeholder="e.g. Neurology" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Qualifications / Board Credentials</label>
                <input name="qualification" placeholder="MD, Specialist Board Certified" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Direct Contact Phone</label>
                <input name="phone" placeholder="+251 9XX XXX XXX" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
            </div>
            <button class="w-full py-3 bg-medical-700 hover:bg-medical-600 text-white font-bold text-xs rounded-xl mt-2">
                Register Doctor
            </button>
        </form>
    </div>
</div>

<!-- Add Service Modal -->
<div id="add-service-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
        <button onclick="document.getElementById('add-service-modal').classList.add('hidden')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">✕</button>
        <h3 class="font-extrabold text-medical-900 text-lg mb-4">Add Medical Service</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_service">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Service Title *</label>
                <input name="name" placeholder="e.g. Dermatology Consult" required class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Category</label>
                    <select name="category" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
                        <option>Clinical</option>
                        <option>Diagnostics</option>
                        <option>Imaging</option>
                        <option>Pharmacy</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Rate (ETB)</label>
                    <input name="price" type="number" placeholder="1000" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none" placeholder="Brief service summary..."></textarea>
            </div>
            <button class="w-full py-3 bg-medical-700 hover:bg-medical-600 text-white font-bold text-xs rounded-xl">
                Add Service
            </button>
        </form>
    </div>
</div>

<!-- Add Article Modal -->
<div id="add-article-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
        <button onclick="document.getElementById('add-article-modal').classList.add('hidden')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">✕</button>
        <h3 class="font-extrabold text-medical-900 text-lg mb-4">Publish Health Article</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_article">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Article Headline *</label>
                <input name="title" required placeholder="e.g. Caring for Hypertension" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Category</label>
                    <select name="category" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
                        <option>Prevention</option>
                        <option>Family Health</option>
                        <option>Wellness</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Publish Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
                        <option>Published</option>
                        <option>Draft</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Author Name</label>
                <input name="author" value="Dr. Hana Tesfaye" class="w-full px-3 py-2 border border-slate-300 rounded-xl text-sm outline-none">
            </div>
            <button class="w-full py-3 bg-medical-700 hover:bg-medical-600 text-white font-bold text-xs rounded-xl">
                Save Article
            </button>
        </form>
    </div>
</div>

</body>
</html>