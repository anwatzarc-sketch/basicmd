<?php

declare(strict_types=1);

/**
 * Afaan Oromo (Afaan Oromoo) UI strings.
 *
 * ############################################################
 * ##  UNVERIFIED DRAFT - NOT CLEARED FOR PRODUCTION USE.    ##
 * ############################################################
 *
 * This dictionary was produced by machine translation. It has NOT been
 * checked by a fluent Afaan Oromo speaker. Before this locale is exposed
 * to patients it MUST be reviewed line by line by a native speaker with
 * health-sector experience, with particular attention to:
 *
 *   - clinical wording          (service_category.*, about.*, faq.*, articles.disclaimer)
 *   - the booking flow          (booking.*, confirmation.*, lookup.*)
 *   - money and payment wording (payment.*, payment_status.*, payment_kind.*, email.payment_*)
 *
 * Wrong wording in those sections can cause a patient to miss an
 * appointment or to pay the wrong amount. Treat every string below as a
 * proposal, not as approved copy.
 *
 * Standardised terminology used throughout (keep consistent when editing):
 *   appointment / booking  = beellama        doctor      = doktora
 *   to book                = qabachuu        patient     = dhukkubsataa
 *   service                = tajaajila       package     = paakeejii
 *   payment                = kaffaltii       deposit     = kaffaltii duraa
 *   booking reference      = koodii beellamaa            receipt = nagahee
 *   specialist             = ogeessa addaa   specialty   = ogummaa
 *   emergency              = ariifachiisaa   clinic(al)  = kilinikaa
 *   health                 = fayyaa          care        = kunuunsa
 *   reception              = iddoo simannaa  to confirm  = mirkaneessuu
 *
 * Key structure mirrors en.php exactly. bin/check-translations.php compares
 * every dictionary against English and fails when a key is missing on
 * either side, so a half-finished translation cannot silently ship blank
 * labels to patients.
 */
return [
    // --- Navigation & chrome -----------------------------------------
    'nav' => [
        'home'       => 'Fuula Duraa',
        'about'      => 'Waa\'ee Keenya',
        'services'   => 'Tajaajiloota',
        'doctors'    => 'Doktoroota',
        'facilities' => 'Dhaabbilee',
        'packages'   => 'Paakeejii Fayyaa',
        'articles'   => 'Barruu Fayyaa',
        'locations'  => 'Bakkeewwan',
        'contact'    => 'Nu Qunnamaa',
        'book'       => 'Beellama Qabadhaa',
        'my_booking' => 'Beellama Koo',
        'menu'       => 'Baafata',
        'close'      => 'Cufi',
        'language'   => 'Afaan',
        'theme'        => 'Bifa',
        'theme_system' => 'Akka sirnaatti',
        'theme_light'  => 'Ifaa',
        'theme_dark'   => 'Dukkanaa',
    ],

    // --- Site assistant -----------------------------------------------
    'chat' => [
        'title'       => 'Kilinika gaafadhu',
        'open'        => 'Gargaaraa bani',
        'close'       => 'Gargaaraa cufi',
        'greeting'    => 'Akkam. Tajaajiloota, doktoroota, sa\'aatii hojii fi akkaataa beellama qabachuu akka argattan isin gargaaruu nan danda\'a. Maal barbaaddu?',
        'placeholder' => 'Waa\'ee tajaajilaa, sa\'aatii yookaan beellamaa gaafadhu',
        'send'        => 'Ergi',
        'thinking'    => 'Barreessaa jira…',
        'you'         => 'Ati',
        'assistant'   => 'Gargaaraa',
        'disclaimer'  => 'Kun gargaaraa ofumaa hojjetuudha malee doktora miti. Gorsa yaalaa kennuu yookaan beellama kee ilaaluu hin danda\'u. Maaloo odeeffannoo fayyaa dhuunfaa hin barreessin.',
        'emergency'   => 'Kun haala ariifachiisaa ta\'uu danda\'a. Maaloo haasaa kana dhaabiitii amma bilbili yookaan gara kutaa ariifachiisaa dhihootti deemi:',
        'unavailable' => 'Dhiifama, gargaaraan yeroo ammaa hin argamu. Maaloo fuula quunnamtii fayyadami yookaan nu bilbili:',
        'error'       => 'Rakkoon uumameera. Maaloo irra deebi\'ii yaali.',
    ],

    // --- Hero ---------------------------------------------------------
    'hero' => [
        'eyebrow'      => 'Kunuunsa amanamaa | :city',
        'title'        => 'Tajaajila fayyaa addaa.',
        'title_accent' => 'Isiniif qophaa\'e.',
        'lead'         => 'Ogeessota addaa gara laafessa, qorannoo ammayyaa fi kunuunsa walsimataa isiniif fi maatii keessaniif.',
        'cta_book'     => 'Beellama Qabadhaa',
        'cta_call'     => 'Nuu Bilbilaa',
        'stat_specialists' => 'Ogeessota addaa',
        'stat_services'    => 'Tajaajila kunuunsaa',
        'stat_emergency'   => 'Sarara ariifachiisaa',
        'badge'     => 'Kunuunsa isin dhaggeeffatu',
        'trust'     => 'Kunuunsa dhukkubsataa dursu',
        'trust_sub' => 'Gara laafessaa fi ragaa irratti hundaa\'e',
    ],

    // --- Sections -----------------------------------------------------
    'about' => [
        'eyebrow' => 'Maaliif nu filattu',
        'title'   => 'Tajaajila fayyaa amanamummaa, ogummaa fi mijataa irratti ijaarame.',
        'point_1_title' => 'Ogeessota addaa muuxannoo qaban',
        'point_1_body'  => 'Ogeessota damee adda addaa irraa walitti dhufan kanneen yaala ragaa irratti hundaa\'e irratti xiyyeeffatan.',
        'point_2_title' => 'Qorannoo ammayyaa',
        'point_2_body'  => 'Tajaajila laaboraatoorii fi iimejingii bakka tokkotti.',
        'point_3_title' => 'Kunuunsa nama giddu-galeessa godhate',
        'point_3_body'  => 'Wal qunnamtii ifa ta\'ee fi tajaajila dhukkubsataaf kabaja qabu.',
        'point_4_title' => 'Argannoo salphaa',
        'point_4_body'  => 'Beellama salphaatti qabachuu fi odeeffannoo bakka argamaa ifa ta\'e.',
    ],

    'services' => [
        'eyebrow'    => 'Tajaajila yaalaa',
        'title'      => 'Fayyaa guyyaa guyyaatiif kunuunsa guutuu.',
        'cta'        => 'Beellama gaafadhaa',
        'all'        => 'Tajaajila hunda',
        'search'     => 'Tajaajila barbaadi',
        'none'       => 'Barbaacha keessaniin walsimu tajaajilli hin argamne.',
        'from_price' => ':price irraa jalqabee',
        'duration'   => 'Naannoo :duration',
        'book_this'  => 'Tajaajila kana qabadhaa',
    ],

    'doctors' => [
        'eyebrow'    => 'Ogeessota keenya',
        'title'      => 'Namoota kunuunsa keessan duuba jiran wal baraa.',
        'all'        => 'Ogummaa hunda',
        'search'     => 'Maqaa ykn ogummaadhaan barbaadi',
        'none'       => 'Barbaacha keessaniin walsimu doktorri hin argamne.',
        'experience' => 'Muuxannoo waggaa :years',
        'experience_one' => 'Muuxannoo waggaa 1',
        'book_with'  => ':name waliin beellama qabadhaa',
        'on_leave'   => 'Yeroo ammaa boqonnaa irra jira',
        'view'       => 'Odeeffannoo guutuu ilaali',
    ],

    'facilities' => [
        'eyebrow' => 'Dhaabbilee keenya',
        'title'   => 'Kunuunsa caalaatiif naannoo tasgabbaa\'aa fi ammayyaa.',
    ],

    'packages' => [
        'eyebrow'      => 'Paakeejii fayyaa',
        'title'        => 'Kunuunsa ittisaa salphaatti.',
        'lead'         => 'Paakeejii qorannoo fayyaa ittisa, dursanii adda baasuu fi jireenya fayya-qabeessa irratti xiyyeeffate filadhaa.',
        'includes'     => 'Wanti hammatame',
        'book'         => 'Paakeejii kana qabadhaa',
        'deposit_note' => 'Kaffaltii duraa :percent (:amount) kaffaluun qabadhaa',
        'pay_full'     => 'Ykn :amount guutuu kaffalaa',
        'most_popular' => 'Baay\'ee filatamaa',
        'none'         => 'Yeroo ammaa paakeejiin fayyaa hin jiru.',
    ],

    'articles' => [
        'eyebrow'     => 'Beekumsa fayyaa',
        'title'       => 'Garee kunuunsa keenya irraa qajeelfama gaarii.',
        'read'        => 'Barruu dubbisi',
        'read_time'   => 'Dubbisa daqiiqaa :minutes',
        'all'         => 'Mata duree hunda',
        'search'      => 'Barruu barbaadi',
        'none'        => 'Barruun hin argamne.',
        'related'     => 'Dubbisa walqabatu',
        'written_by'  => 'Barreessaa',
        'reviewed_by' => 'Ogeessa fayyaatiin kan sakatta\'ame',
        'published'   => 'Kan maxxanfame :date',
        'updated'     => 'Kan haaromfame :date',
        'back'        => 'Gara Barruu Fayyaatti deebi\'i',
        'disclaimer'  => 'Barruun kun odeeffannoo waliigalaatiif kan dhiyaate yoo ta\'u, marii ogeessa fayyaa gahumsa qabu waliin taasifamu hin bakka bu\'u.',
    ],

    'locations' => [
        'eyebrow'    => 'Nu daawwadhaa',
        'title'      => ':city keessatti bakka mijataa irratti argamna.',
        'address'    => 'Teessoo',
        'phone'      => 'Bilbila',
        'email'      => 'Iimeelii',
        'hours'      => 'Sa\'aatii hojii',
        'directions' => 'Karaa argadhaa',
        'open_maps'  => 'Google Maps irratti bani',
    ],

    'contact' => [
        'eyebrow' => 'Nu qunnamaa',
        'title'   => 'Isin gargaaruuf as jirra.',
        'lead'    => 'Waa\'ee tajaajila keenyaa, michoota inshuraansii ykn sagantaa ogeessotaa gaaffii qabduu? Ergaa nuuf ergaa; gareen keenya dafee deebii isiniif kenna.',
        'sent'    => 'Galatoomaa. Ergaan keessan nu gaheera; gareen keenya dafee deebii isiniif kenna.',
    ],

    'faq' => [
        'title' => 'Gaaffilee yeroo baay\'ee gaafataman',
        'q1'    => 'Dhukkubsattoota beellama malee dhufan ni simattuu?',
        'a1'    => 'Eeyyee. Marii waliigalaatiif beellama malee dhufuun ni danda\'ama; haa ta\'u malee duraan beellama qabachuun yeroo eegumsaa ni hir\'isa.',
        'q2'    => 'Beellamni akkamitti mirkanaa\'a?',
        'a2'    => 'Yeruma sana koodii beellama keessanii kan qabu iimeelii isin gaha. Itti aansuudhaan qindeessaan keenya sa\'aatii sirrii bilbilaan ykn iimeeliidhaan isiniif mirkaneessa.',
        'q3'    => 'Tajaajila ariifachiisaa sa\'aatii 24/7 ni kennituu?',
        'a3'    => 'Eeyyee. Gareen ariifachiisaa keenya sa\'aatii 24 guutuu qophiidha. Gargaarsa hatattamaa yoo barbaaddan maaloo kallattiin sarara ariifachiisaa keenyatti bilbilaa.',
        'q4'    => 'Afaan Amaaraatiin yaalamuu nan danda\'aa?',
        'a4'    => 'Eeyyee. Ogeessonni yaalaa keenyaa fi bakka bu\'oonni tajaajila dhukkubsataa hundi Afaan Amaaraa fi Ingiliffaan sirriitti dubbatu.',
        'q5'    => 'Paakeejii fayyaatiif akkamitti kaffaluu danda\'a?',
        'a5'    => 'Erga beellama qabattanii booda odeeffannoon baankii fi maallaqa moobaayilii keenyaa isinitti mul\'ata. Maallaqa dabarsaa, nagahee keessan ol kaa\'aa; gareen faayinaansii keenyaas guyyaa hojii tokko keessatti mirkaneessa.',
    ],

    // --- Booking flow -------------------------------------------------
    'booking' => [
        'eyebrow'      => 'Gaaffii beellamaa',
        'title'        => 'Akkamitti akka isin gargaaruu dandeenyu nutti himaa.',
        'lead'         => 'Tajaajila, doktora, guyyaa fi sa\'aatii filadhaa. Iimeelii mirkaneessaa koodii beellama keessanii qabu ni argattu.',
        'step_details' => 'Ibsa beellamaa',
        'step_patient' => 'Odeeffannoo keessan',
        'step_payment' => 'Kaffaltii',
        'step_done'    => 'Mirkanaa\'e',

        'service'       => 'Tajaajila',
        'service_ph'    => 'Tajaajila filadhaa',
        'package'       => 'Paakeejii fayyaa',
        'package_ph'    => 'Paakeejii filadhaa',
        'doctor'        => 'Doktora',
        'doctor_any'    => 'Doktora argamu kamiyyuu',
        'date'          => 'Guyyaa filattan',
        'time'          => 'Sa\'aatii filattan',
        'time_ph'       => 'Yeroo filadhaa',
        'tier'          => 'Gosa tarree',
        'tier_standard' => 'Idilee',
        'tier_express'  => 'Dursa (+:percent)',
        'tier_express_hint' => 'Yeroo keessan keessatti jalqaba ilaalamtu.',

        'name'      => 'Maqaa guutuu',
        'name_ph'   => 'Maqaa guutuu dhukkubsataa',
        'phone'     => 'Lakkoofsa bilbilaa',
        'phone_ph'  => '09XX XXX XXX',
        'phone_hint'=> 'Beellama keessan mirkaneessuuf kana fayyadamna.',
        'email'     => 'Teessoo iimeelii',
        'email_ph'  => 'name@example.com',
        'email_hint'=> 'Mirkaneessaa fi yaadachiisni keessan asitti ergama.',
        'notes'     => 'Ergaa / yaadannoo',
        'notes_ph'  => 'Mallattoo dhukkubaa keessan ykn gaaffii addaa ibsaa (filannoo)',

        'submit'     => 'Beellama Mirkaneessi',
        'submitting' => 'Mirkaneessaa jira...',

        'slots_left'     => 'Bakki :count hafe',
        'slot_full'      => 'Guutuudha',
        'slot_available' => 'Ni argama',
        'checking'       => 'Bakka banaa sakatta\'aa jira...',
        'select_date_first' => 'Sa\'aatii banaa ilaaluuf guyyaa filadhaa.',

        'summary'   => 'Cuunfaa beellamaa',
        'total'     => 'Waliigala',
        'surcharge' => 'Kaffaltii dabalataa dursaa',
        'subtotal'  => 'Ida\'ama xiqqaa',

        'emergency_title' => 'Gargaarsa ariifachiisaa barbaadduu?',
        'emergency_body'  => 'Balaa yaalaa hatattamaa yoo ta\'e, toora interneetiin beellama qabachuu mannaa maaloo kallattiin sarara 24/7 keenyatti bilbilaa.',
    ],

    'confirmation' => [
        'title'        => 'Beellamni keessan qabameera',
        'subtitle'     => 'Ibsa isaa gara :email ergineerra',
        'subtitle_no_email' => 'Maaloo koodii beellama keessanii olkaa\'adhaa.',
        'reference'    => 'Koodii beellamaa',
        'ref_hint'     => 'Yeroo bilbiltan ykn kaffaltii dabarsitan koodii kana fayyadamaa.',
        'what_next'    => 'Itti aansee maaltu ta\'a',
        'next_1'       => 'Gareen simannaa keenya gaaffii keessan ilaalee sa\'aatii sirrii mirkaneessa.',
        'next_2'       => 'Beellama keessaniin dura sa\'aatii 24 dursee yaadachiisni isin gaha.',
        'next_3'       => 'Daqiiqaa 10 dursaatii dhufaa; bu\'aa qorannoo duraanii yoo qabaattan fidaa.',
        'pay_now'      => 'Kaffaltii keessan xumuraa',
        'pay_later'    => 'Yeroo dhuftan iddoo simannaatti kaffaluu dandeessu.',
        'add_calendar' => 'Gara kaalaandarii dabali',
        'print'        => 'Fuula kana maxxansi',
    ],

    // --- Payment ------------------------------------------------------
    'payment' => [
        'title'          => 'Kaffaltii keessan xumuraa',
        'lead'           => 'Hanga armaan gadii karaa kamiinuu dabarsaa, itti aansuudhaan nagahee keessan ol kaa\'aa. Gareen faayinaansii keenya guyyaa hojii tokko keessatti mirkaneessa.',
        'amount_due'     => 'Hanga kaffalamu',
        'deposit_due'    => 'Kaffaltii duraa amma kaffalamu',
        'balance_note'   => ':amount hafe iddoo simannaatti kaffalama.',
        'choose_method'  => 'Mala kaffaltii filadhaa',
        'account_name'   => 'Maqaa herregaa',
        'account_number' => 'Lakkoofsa herregaa',
        'branch'         => 'Damee',
        'copy'           => 'Garagalchi',
        'copied'         => 'Garagalfameera',
        'reference_note' => 'Barbaachisaa: kaffaltii keessan walsimsiisuu akka dandeenyuuf koodii beellama keessanii :ref sababa dabarsaa irratti barreessaa.',

        'upload_title'  => 'Nagahee keessan ol kaa\'aa',
        'upload_lead'   => 'Suuraa nagahee baankii ykn iskiriinshootii mirkaneessa baankii moobaayilii itti dabalaa.',
        'upload_field'  => 'Nagahee ykn iskiriinshootii',
        'upload_hint'   => 'JPG, PNG, WebP ykn PDF. Ol\'aanaan :size MB.',
        'upload_choose' => 'Faayilii filadhaa',
        'upload_drop'   => 'ykn as harkisaatii gadhiisaa',
        'payer_name'    => 'Maqaa herrega itti fayyadamtanii',
        'transfer_ref'  => 'Lakkoofsa dabarsaa / nagahee',
        'transfer_ref_hint' => 'Nagahee baankii keessan ykn mirkaneessa Telebirr irratti argama.',
        'transfer_date' => 'Guyyaa dabarsaa',
        'amount_sent'   => 'Hanga ergame',
        'submit'        => 'Mirkaneessaaf ergi',

        'submitted_title' => 'Nagaheen nu gaheera',
        'submitted_body'  => 'Galatoomaa. Gareen faayinaansii keenya kaffaltii keessan ilaalaa jira; erga mirkanaa\'ee booda iimeelii ni argattu.',
        'cash_title'      => 'Iddoo simannaatti kaffalaa',
        'cash_body'       => 'Dabarsuun hin barbaachisu. Maaloo daqiiqaa 15 dursaatii dhuftanii iddoo simannaa keenyatti kaffalaa.',

        'status_unpaid'    => 'Kaffaltii eegaa jira',
        'status_submitted' => 'Mirkaneessa eegaa jira',
        'status_verified'  => 'Kaffaltiin mirkanaa\'eera',
        'status_rejected'  => 'Kaffaltiin mirkanaa\'uu hin dandeenye',
        'rejected_note'    => 'Sababa: :reason',
        'resubmit'         => 'Nagahee sirreeffame ol kaa\'aa',
    ],

    // --- Booking lookup -----------------------------------------------
    'lookup' => [
        'title'     => 'Beellama keessan ilaalaa',
        'lead'      => 'Koodii beellama keessanii fi lakkoofsa bilbilaa ittiin beellama qabattan galchaa.',
        'reference' => 'Koodii beellamaa',
        'ref_ph'    => 'AMC-XXXXXXXX',
        'phone'     => 'Lakkoofsa bilbilaa',
        'submit'    => 'Beellama koo barbaadi',
        'not_found' => 'Odeeffannoo kanaan beellama argachuu hin dandeenye. Maaloo mirkaneeffattanii irra deebi\'aa yaalaa.',
        'cancel'    => 'Beellama kana haqi',
        'cancel_confirm' => 'Dhugumatti beellama kana haquu barbaadduu?',
        'cancelled' => 'Beellamni keessan haqameera.',
        'cannot_cancel' => 'Beellamni kun toora interneetiin haquun hin danda\'amu. Maaloo nuu bilbilaa.',
    ],

    // --- Enum labels --------------------------------------------------
    'appointment_status' => [
        'pending'   => 'Mirkaneessa eegaa jira',
        'confirmed' => 'Mirkanaa\'eera',
        'completed' => 'Xumurameera',
        'cancelled' => 'Haqameera',
        'no_show'   => 'Hin dhufne',
    ],

    'payment_status' => [
        'unpaid'                => 'Hin kaffalamne',
        'awaiting_verification' => 'Mirkaneessa eegaa jira',
        'deposit_paid'          => 'Kaffaltiin duraa kaffalameera',
        'paid'                  => 'Guutuun kaffalameera',
        'refunded'              => 'Maallaqni deebi\'eera',
        'waived'                => 'Kaffaltiin hin barbaachisu',
    ],

    'proof_status' => [
        'awaiting_proof' => 'Nagahee eegaa jira',
        'submitted'      => 'Ilaalamaa jira',
        'verified'       => 'Mirkanaa\'eera',
        'rejected'       => 'Fudhatama hin arganne',
    ],

    'payment_channel' => [
        'bank_transfer'   => 'Dabarsa baankii',
        'mobile_money'    => 'Maallaqa moobaayilii',
        'cash_on_arrival' => 'Iddoo simannaatti kaffaluu',
    ],

    'payment_kind' => [
        'deposit' => 'Kaffaltii duraa',
        'balance' => 'Kaffaltii hafe',
        'full'    => 'Kaffaltii guutuu',
    ],

    'queue_tier' => [
        'standard' => 'Idilee',
        'express'  => 'Dursa',
    ],

    'time_slot' => [
        '0800_1000' => '08:00 - 10:00',
        '1000_1200' => '10:00 - 12:00',
        '1400_1600' => '14:00 - 16:00',
        '1600_1800' => '16:00 - 18:00',
    ],

    'service_category' => [
        'clinical'    => 'Kunuunsa kilinikaa',
        'diagnostics' => 'Qorannoo',
        'imaging'     => 'Iimejingii',
        'pharmacy'    => 'Faarmaasii',
        'wellness'    => 'Fayyummaa',
        'emergency'   => 'Ariifachiisaa',
    ],

    'doctor_status' => [
        'active'   => 'Ni argama',
        'inactive' => 'Hin argamu',
        'on_leave' => 'Boqonnaa irra',
    ],

    'facility_status' => [
        'operational' => 'Hojii irra',
        'maintenance' => 'Suphaa irra',
        'upgrading'   => 'Fooyya\'aa jira',
        'offline'     => 'Cufaadha',
    ],

    // --- Forms & validation -------------------------------------------
    'form' => [
        'required' => 'Barbaachisaa',
        'optional' => 'Filannoo',
        'submit'   => 'Ergi',
        'cancel'   => 'Dhiisi',
        'save'     => 'Olkaa\'i',
        'back'     => 'Duubatti',
        'next'     => 'Itti fufi',
        'search'   => 'Barbaadi',
        'filter'   => 'Calali',
        'clear'    => 'Qulqulleessi',
        'select'   => 'Filadhaa',
        'yes'      => 'Eeyyee',
        'no'       => 'Lakki',
    ],

    'validation' => [
        'required'        => 'Maaloo :field keessan galchaa.',
        'required_select' => 'Maaloo :field filadhaa.',
        'email'           => 'Maaloo teessoo iimeelii sirrii galchaa.',
        'phone'           => 'Maaloo lakkoofsa bilbilaa Itoophiyaa sirrii galchaa, fakkeenyaaf 0911 123 456.',
        'date'            => 'Maaloo guyyaa sirrii galchaa.',
        'min'             => ':field yoo xiqqaate qubee :min ta\'uu qaba.',
        'max'             => ':field qubee :max caaluu hin qabu.',
        'file_type'       => 'Maaloo faayilii JPG, PNG, WebP ykn PDF ol kaa\'aa.',
        'file_size'       => 'Faayiliin kun baay\'ee guddaadha. Hangi ol\'aanaan :size MB.',
        'file_required'   => 'Maaloo nagahee keessan itti dabalaa.',
        'generic'         => 'Maaloo dirreewwan mul\'ifaman sirreessaa.',
        'csrf'            => 'Sababa nageenyaatiif yeroon seensa keessanii xumurameera. Maaloo irra deebi\'aa yaalaa.',
        'rate_limited'    => 'Yaaliin baay\'eedha. Maaloo xiqqoo eegaatii irra deebi\'aa yaalaa.',
    ],

    // --- Errors -------------------------------------------------------
    'errors' => [
        '404_title' => 'Fuulli hin argamne',
        '404_body'  => 'Fuulli barbaaddan hin jiru ykn iddoo isaa jijjiirameera.',
        '403_title' => 'Seensi dhorkameera',
        '403_body'  => 'Fuula kana ilaaluuf hayyama hin qabdan.',
        '419_title' => 'Yeroon seensaa xumurameera',
        '419_body'  => 'Nageenya keessaniif jecha yeroon seensa keessanii xumurameera. Maaloo duubatti deebi\'aatii yaalaa.',
        '429_title' => 'Gaaffiin baay\'eedha',
        '429_body'  => 'Maaloo xiqqoo eegaatii irra deebi\'aa yaalaa.',
        '500_title' => 'Rakkoon uumameera',
        '500_body'  => 'Rakkoon hin eegamne nu mudateera. Gareen keenya beeksifameera. Maaloo yeroo gabaabaa booda irra deebi\'aa yaalaa.',
        'home'      => 'Gara fuula duraatti deebi\'i',
        'call_us'   => 'Ykn :phone irratti nuu bilbilaa',
    ],

    // --- Footer -------------------------------------------------------
    'footer' => [
        'about'   => ':city keessatti tajaajila fayyaa ogummaa fi gara laafina qabu. Dhaabbilee ammayyaa fi hojjettoota yaalaa ogeeyyii, fayyummaa keessaniif kan qophaa\'an.',
        'explore' => 'Sakatta\'aa',
        'contact' => 'Nu qunnamaa',
        'legal'   => 'Seera',
        'privacy' => 'Imaammata dhuunfummaa',
        'terms'   => 'Haala tajaajilaa',
        'rights'  => 'Mirgi hundi seeraan eegamaadha.',
        'staff'   => 'Seensa hojjettootaa',
    ],

    // --- Email --------------------------------------------------------
    'email' => [
        'greeting'     => 'Kabajamoo :name,',
        'signoff'      => 'Nagaa wajjin,',
        'team'         => 'Garee Wiirtuu Yaalaa MediCareMini',
        'auto_note'    => 'Ergaan kun ofumaan kan ergamedha. Maaloo kallattiin deebii hin kenninaa.',
        'contact_note' => 'Gaaffii qabduu? :phone irratti bilbilaa ykn gara :email barreessaa.',

        'booked_subject' => 'Beellamni keessan qabameera - :ref',
        'booked_heading' => 'Beellamni keessan qabameera',
        'booked_intro'   => 'Wiirtuu Yaalaa MediCareMini waan filattaniif galatoomaa. Ibsi beellama keessanii armaan gadii jira.',

        'reminder_subject' => 'Yaadachiisa: beellamni keessan bori - :ref',
        'reminder_heading' => 'Beellamni keessan boridha',
        'reminder_intro'   => 'Kun waa\'ee beellama keessan dhufuuf jiruu yaadachiisa.',
        'reminder_tips'    => 'Maaloo daqiiqaa 10 dursaatii dhufaa; bu\'aa qorannoo duraanii ykn qoricha amma fudhataa jirtan fidaa.',

        'confirmed_subject' => 'Beellamni mirkanaa\'eera - :ref',
        'confirmed_heading' => 'Beellamni keessan mirkanaa\'eera',
        'confirmed_intro'   => 'Gareen simannaa keenya beellama keessan mirkaneesseera.',

        'cancelled_subject' => 'Beellamni haqameera - :ref',
        'cancelled_heading' => 'Beellamni keessan haqameera',
        'cancelled_intro'   => 'Beellamni keessan haqameera. Kun kan isin hin eegne yoo ta\'e maaloo nu qunnamaa.',

        'followup_subject' => 'Erga nu daawwattanii booda akkam jirtu?',
        'followup_heading' => 'Nu waan daawwattaniif galatoomaa',
        'followup_intro'   => 'Fooyya\'aa jirtu jennee abdanna. Beellama hordoffii yoo barbaaddan ykn waa\'ee bu\'aa qorannoo keessanii gaaffii yoo qabaattan, isin gargaaruuf as jirra.',
        'followup_cta'     => 'Beellama hordoffii qabadhaa',

        'payment_received_subject' => 'Nagaheen keessan nu gaheera - :ref',
        'payment_received_heading' => 'Nagaheen nu gaheera',
        'payment_received_intro'   => 'Galatoomaa. Nagaheen kaffaltii keessanii nu gaheera; gareen faayinaansii keenyas ilaalaa jira. Guyyaa hojii tokko keessatti deebii ni argattu.',

        'payment_verified_subject' => 'Kaffaltiin mirkanaa\'eera - :ref',
        'payment_verified_heading' => 'Kaffaltiin keessan mirkanaa\'eera',
        'payment_verified_intro'   => 'Kaffaltii keessan mirkaneessineerra. Beellamni keessan guutummaatti qabameera.',

        'payment_rejected_subject' => 'Kaffaltii keessan irratti tarkaanfiin barbaachisa - :ref',
        'payment_rejected_heading' => 'Kaffaltii keessan mirkaneessuu hin dandeenye',
        'payment_rejected_intro'   => 'Dhiifama, nagahee ol kaa\'attan mirkaneessuu hin dandeenye.',
        'payment_rejected_action'  => 'Maaloo hidhaa armaan gadiitiin nagahee sirreeffame ol kaa\'aa; ykn akka isin gargaarruuf nuu bilbilaa.',

        'inquiry_subject' => 'Ergaan keessan nu gaheera',
        'inquiry_heading' => 'Nu waan qunnamtaniif galatoomaa',
        'inquiry_intro'   => 'Ergaan keessan nu gaheera; miseensi garee keenyaa dafee deebii isiniif kenna.',

        'label_reference' => 'Koodii beellamaa',
        'label_service'   => 'Tajaajila',
        'label_doctor'    => 'Doktora',
        'label_date'      => 'Guyyaa',
        'label_time'      => 'Sa\'aatii',
        'label_amount'    => 'Hanga',
        'label_paid'      => 'Kan kaffalame',
        'label_balance'   => 'Kaffaltii hafe',
        'label_status'    => 'Haala',
        'label_location'  => 'Bakka',
        'label_reason'    => 'Sababa',

        'view_booking' => 'Beellama koo ilaali',
        'pay_now'      => 'Kaffaltii xumuri',
    ],

    // --- Misc ---------------------------------------------------------
    'common' => [
        'loading'   => 'Fe\'aa jira...',
        'from'      => 'Irraa',
        'free'      => 'Bilisa',
        'etb'       => 'Birrii',
        'today'     => 'Har\'a',
        'tomorrow'  => 'Bori',
        'view_all'  => 'Hunda ilaali',
        'show_more' => 'Dabalata agarsiisi',
        'show_less' => 'Xiqqeessi',
        'skip_to_content' => 'Gara qabiyyee ijoo darbi',
        'ethiopian_date'  => 'Guyyaa Itoophiyaa',
    ],
];
