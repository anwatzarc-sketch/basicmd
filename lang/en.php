<?php

declare(strict_types=1);

/**
 * English UI strings.
 *
 * Keys are dot-namespaced and resolved by Translator::get(). Every key here
 * must also exist in am.php; bin/check-translations.php fails the build when
 * one drifts.
 *
 * Placeholders use :name and are substituted positionally by name, never by
 * order, so a translation may reorder them freely.
 */
return [
    // --- Navigation & chrome -----------------------------------------
    'nav' => [
        'home'       => 'Home',
        'about'      => 'About',
        'services'   => 'Services',
        'doctors'    => 'Doctors',
        'facilities' => 'Facilities',
        'packages'   => 'Health Packages',
        'articles'   => 'Health Hub',
        'locations'  => 'Locations',
        'contact'    => 'Contact',
        'book'       => 'Book Appointment',
        'my_booking' => 'My Booking',
        'menu'       => 'Menu',
        'close'      => 'Close',
        'language'   => 'Language',
        'theme'        => 'Theme',
        'theme_system' => 'Match system',
        'theme_light'  => 'Light',
        'theme_dark'   => 'Dark',
    ],

    // --- Site assistant -----------------------------------------------
    'chat' => [
        'title'       => 'Ask the clinic',
        'open'        => 'Open the assistant',
        'close'       => 'Close the assistant',
        'greeting'    => 'Hello. I can help you find services, doctors, opening hours and how booking works. What are you looking for?',
        'placeholder' => 'Ask about services, hours or booking',
        'send'        => 'Send',
        'thinking'    => 'Typing…',
        'you'         => 'You',
        'assistant'   => 'Assistant',
        'disclaimer'  => 'An automated assistant, not a clinician. It cannot give medical advice or see your booking. Please do not type personal health details.',
        'emergency'   => 'This may be an emergency. Please stop using this chat and call now, or go to the nearest emergency department:',
        'unavailable' => 'Sorry, the assistant is unavailable right now. Please use the contact page or call us:',
        'error'       => 'Something went wrong. Please try again.',
    ],

    // --- Hero ---------------------------------------------------------
    'hero' => [
        'eyebrow'   => 'Trusted care | :city',
        'title'     => 'Exceptional healthcare.',
        'title_accent' => 'Designed around you.',
        'lead'      => 'Compassionate specialists, modern diagnostics and coordinated care for you and your family.',
        'cta_book'  => 'Book Appointment',
        'cta_call'  => 'Call Us',
        'stat_specialists' => 'Specialists',
        'stat_services'    => 'Care services',
        'stat_emergency'   => 'Emergency line',
        'badge'     => 'Care that listens',
        'trust'     => 'Patient-first care',
        'trust_sub' => 'Compassionate & evidence-based',
    ],

    // --- Sections -----------------------------------------------------
    'about' => [
        'eyebrow' => 'Why choose us',
        'title'   => 'Healthcare built on trust, expertise and comfort.',
        'point_1_title' => 'Experienced specialists',
        'point_1_body'  => 'Multidisciplinary clinicians focused on evidence-based care.',
        'point_2_title' => 'Modern diagnostics',
        'point_2_body'  => 'Convenient laboratory and imaging services under one roof.',
        'point_3_title' => 'Human-centered care',
        'point_3_body'  => 'Clear communication and a respectful patient experience.',
        'point_4_title' => 'Convenient access',
        'point_4_body'  => 'Simple appointment booking and easy-to-find location information.',
    ],

    'services' => [
        'eyebrow'     => 'Medical services',
        'title'       => 'Complete care for everyday health.',
        'cta'         => 'Request an appointment',
        'all'         => 'All services',
        'search'      => 'Search services',
        'none'        => 'No services match your search.',
        'from_price'  => 'From :price',
        'duration'    => 'About :duration',
        'book_this'   => 'Book this service',
    ],

    'doctors' => [
        'eyebrow'    => 'Our specialists',
        'title'      => 'Meet the people behind your care.',
        'all'        => 'All specialties',
        'search'     => 'Search by name or specialty',
        'none'       => 'No doctors match your search.',
        'experience' => ':years years experience',
        'experience_one' => '1 year experience',
        'book_with'  => 'Book with :name',
        'on_leave'   => 'Currently on leave',
        'view'       => 'View profile',
    ],

    'facilities' => [
        'eyebrow' => 'Our facilities',
        'title'   => 'A calm, modern environment for better care.',
    ],

    'packages' => [
        'eyebrow'        => 'Health packages',
        'title'          => 'Preventive care made simple.',
        'lead'           => 'Choose a health screening package designed around prevention, early detection and lifestyle wellness.',
        'includes'       => 'What is included',
        'book'           => 'Book this package',
        'deposit_note'   => 'Reserve with a :percent deposit of :amount',
        'pay_full'       => 'Or pay the full :amount',
        'most_popular'   => 'Most popular',
        'none'           => 'No health packages are available right now.',
    ],

    'articles' => [
        'eyebrow'     => 'Health knowledge',
        'title'       => 'Useful guidance from our care team.',
        'read'        => 'Read article',
        'read_time'   => ':minutes min read',
        'all'         => 'All topics',
        'search'      => 'Search articles',
        'none'        => 'No articles found.',
        'related'     => 'Related reading',
        'written_by'  => 'Written by',
        'reviewed_by' => 'Medically reviewed by',
        'published'   => 'Published :date',
        'updated'     => 'Updated :date',
        'back'        => 'Back to Health Hub',
        'disclaimer'  => 'This article is for general information and does not replace a consultation with a qualified clinician.',
    ],

    'locations' => [
        'eyebrow'  => 'Visit us',
        'title'    => 'Conveniently located in :city.',
        'address'  => 'Address',
        'phone'    => 'Phone',
        'email'    => 'Email',
        'hours'    => 'Opening hours',
        'directions' => 'Get directions',
        'open_maps'  => 'Open in Google Maps',
    ],

    'contact' => [
        'eyebrow' => 'Contact us',
        'title'   => 'We are here to help.',
        'lead'    => 'Have a question about our services, insurance partners or specialist schedules? Send a message and our team will get back to you.',
        'sent'    => 'Thank you. Your message has been sent and our team will respond shortly.',
    ],

    'faq' => [
        'title' => 'Frequently asked questions',
        'q1'    => 'Do you accept walk-in patients?',
        'a1'    => 'Yes. Walk-ins are welcome for general consultations, though booking ahead minimises waiting time.',
        'q2'    => 'How are appointments confirmed?',
        'a2'    => 'You receive an email immediately with your booking reference. Our intake coordinator then confirms your exact time by phone or email.',
        'q3'    => 'Do you provide 24/7 emergency services?',
        'a3'    => 'Yes. Our emergency team is available around the clock. For immediate assistance please call the emergency hotline directly.',
        'q4'    => 'Can I be seen in Amharic?',
        'a4'    => 'Yes. All our clinical staff and patient service representatives communicate fluently in both Amharic and English.',
        'q5'    => 'How do I pay for a health package?',
        'a5'    => 'After booking you will see our bank and mobile-money details. Transfer the amount, upload your receipt, and our finance team confirms it within one working day.',
    ],

    // --- Booking flow -------------------------------------------------
    'booking' => [
        'eyebrow'        => 'Appointment request',
        'title'          => 'Tell us how we can help.',
        'lead'           => 'Choose your service, doctor, date and time. You will receive an email confirmation with your booking reference.',
        'step_details'   => 'Appointment details',
        'step_patient'   => 'Your details',
        'step_payment'   => 'Payment',
        'step_done'      => 'Confirmed',

        'service'        => 'Service',
        'service_ph'     => 'Select a service',
        'package'        => 'Health package',
        'package_ph'     => 'Select a package',
        'doctor'         => 'Doctor',
        'doctor_any'     => 'Any available doctor',
        'date'           => 'Preferred date',
        'time'           => 'Preferred time',
        'time_ph'        => 'Select a time block',
        'tier'           => 'Queue type',
        'tier_standard'  => 'Standard',
        'tier_express'   => 'Express priority (+:percent)',
        'tier_express_hint' => 'Be seen first in your time block.',

        'name'           => 'Full name',
        'name_ph'        => 'Patient full name',
        'phone'          => 'Phone number',
        'phone_ph'       => '09XX XXX XXX',
        'phone_hint'     => 'We use this to confirm your appointment.',
        'email'          => 'Email address',
        'email_ph'       => 'name@example.com',
        'email_hint'     => 'Your confirmation and reminder are sent here.',
        'notes'          => 'Message / notes',
        'notes_ph'       => 'Describe your symptoms or any specific request (optional)',

        'submit'         => 'Confirm Appointment',
        'submitting'     => 'Confirming...',

        'slots_left'     => ':count places left',
        'slot_full'      => 'Fully booked',
        'slot_available' => 'Available',
        'checking'       => 'Checking availability...',
        'select_date_first' => 'Choose a date to see available times.',

        'summary'        => 'Booking summary',
        'total'          => 'Total',
        'surcharge'      => 'Express priority surcharge',
        'subtotal'       => 'Subtotal',

        'emergency_title' => 'Need emergency assistance?',
        'emergency_body'  => 'For urgent medical emergencies please call our 24/7 dispatch line directly instead of booking online.',
    ],

    'confirmation' => [
        'title'        => 'Your appointment is booked',
        'subtitle'     => 'We have sent the details to :email',
        'subtitle_no_email' => 'Please save your booking reference.',
        'reference'    => 'Booking reference',
        'ref_hint'     => 'Quote this reference when you call or transfer payment.',
        'what_next'    => 'What happens next',
        'next_1'       => 'Our intake team reviews your request and confirms your exact time.',
        'next_2'       => 'You receive a reminder 24 hours before your appointment.',
        'next_3'       => 'Arrive 10 minutes early and bring any previous test results.',
        'pay_now'      => 'Complete your payment',
        'pay_later'    => 'You can pay at reception when you arrive.',
        'add_calendar' => 'Add to calendar',
        'print'        => 'Print this page',
    ],

    // --- Payment ------------------------------------------------------
    'payment' => [
        'title'          => 'Complete your payment',
        'lead'           => 'Transfer the amount below using any of the methods, then upload your receipt. Our finance team verifies it within one working day.',
        'amount_due'     => 'Amount due',
        'deposit_due'    => 'Deposit due now',
        'balance_note'   => 'The remaining :amount is payable at reception.',
        'choose_method'  => 'Choose a payment method',
        'account_name'   => 'Account name',
        'account_number' => 'Account number',
        'branch'         => 'Branch',
        'copy'           => 'Copy',
        'copied'         => 'Copied',
        'reference_note' => 'Important: write your booking reference :ref in the transfer reason so we can match your payment.',

        'upload_title'   => 'Upload your receipt',
        'upload_lead'    => 'Attach a photo of the bank slip or a screenshot of your mobile-banking confirmation.',
        'upload_field'   => 'Receipt or screenshot',
        'upload_hint'    => 'JPG, PNG, WebP or PDF. Maximum :size MB.',
        'upload_choose'  => 'Choose file',
        'upload_drop'    => 'or drag and drop it here',
        'payer_name'     => 'Name on the account used',
        'transfer_ref'   => 'Transaction / receipt number',
        'transfer_ref_hint' => 'Found on your bank slip or Telebirr confirmation.',
        'transfer_date'  => 'Date of transfer',
        'amount_sent'    => 'Amount sent',
        'submit'         => 'Submit for verification',

        'submitted_title' => 'Receipt received',
        'submitted_body'  => 'Thank you. Our finance team is reviewing your payment and you will receive an email once it is confirmed.',
        'cash_title'      => 'Pay at reception',
        'cash_body'       => 'No transfer needed. Please arrive 15 minutes early to settle the amount at our reception desk.',

        'status_unpaid'    => 'Awaiting payment',
        'status_submitted' => 'Awaiting verification',
        'status_verified'  => 'Payment confirmed',
        'status_rejected'  => 'Payment could not be verified',
        'rejected_note'    => 'Reason: :reason',
        'resubmit'         => 'Upload a corrected receipt',
    ],

    // --- Booking lookup -----------------------------------------------
    'lookup' => [
        'title'     => 'Check your booking',
        'lead'      => 'Enter your booking reference and the phone number you booked with.',
        'reference' => 'Booking reference',
        'ref_ph'    => 'AMC-XXXXXXXX',
        'phone'     => 'Phone number',
        'submit'    => 'Find my booking',
        'not_found' => 'We could not find a booking with those details. Please check and try again.',
        'cancel'    => 'Cancel this appointment',
        'cancel_confirm' => 'Are you sure you want to cancel this appointment?',
        'cancelled' => 'Your appointment has been cancelled.',
        'cannot_cancel' => 'This appointment can no longer be cancelled online. Please call us.',
    ],

    // --- Enum labels --------------------------------------------------
    'appointment_status' => [
        'pending'   => 'Pending confirmation',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show'   => 'Missed',
    ],

    'payment_status' => [
        'unpaid'                => 'Unpaid',
        'awaiting_verification' => 'Awaiting verification',
        'deposit_paid'          => 'Deposit paid',
        'paid'                  => 'Paid in full',
        'refunded'              => 'Refunded',
        'waived'                => 'No payment required',
    ],

    'proof_status' => [
        'awaiting_proof' => 'Awaiting receipt',
        'submitted'      => 'Under review',
        'verified'       => 'Verified',
        'rejected'       => 'Rejected',
    ],

    'payment_channel' => [
        'bank_transfer'   => 'Bank transfer',
        'mobile_money'    => 'Mobile money',
        'cash_on_arrival' => 'Pay at reception',
    ],

    'payment_kind' => [
        'deposit' => 'Deposit',
        'balance' => 'Remaining balance',
        'full'    => 'Full payment',
    ],

    'queue_tier' => [
        'standard' => 'Standard',
        'express'  => 'Express priority',
    ],

    'time_slot' => [
        '0800_1000' => '08:00 - 10:00',
        '1000_1200' => '10:00 - 12:00',
        '1400_1600' => '14:00 - 16:00',
        '1600_1800' => '16:00 - 18:00',
    ],

    'service_category' => [
        'clinical'    => 'Clinical care',
        'diagnostics' => 'Diagnostics',
        'imaging'     => 'Imaging',
        'pharmacy'    => 'Pharmacy',
        'wellness'    => 'Wellness',
        'emergency'   => 'Emergency',
    ],

    'doctor_status' => [
        'active'   => 'Available',
        'inactive' => 'Unavailable',
        'on_leave' => 'On leave',
    ],

    'facility_status' => [
        'operational' => 'Operational',
        'maintenance' => 'Under maintenance',
        'upgrading'   => 'Being upgraded',
        'offline'     => 'Closed',
    ],

    // --- Forms & validation -------------------------------------------
    'form' => [
        'required'        => 'Required',
        'optional'        => 'Optional',
        'submit'          => 'Submit',
        'cancel'          => 'Cancel',
        'save'            => 'Save',
        'back'            => 'Back',
        'next'            => 'Next',
        'search'          => 'Search',
        'filter'          => 'Filter',
        'clear'           => 'Clear',
        'select'          => 'Select',
        'yes'             => 'Yes',
        'no'              => 'No',
    ],

    'validation' => [
        'required'      => 'Please enter your :field.',
        'required_select' => 'Please choose a :field.',
        'email'         => 'Please enter a valid email address.',
        'phone'         => 'Please enter a valid Ethiopian phone number, for example 0911 123 456.',
        'date'          => 'Please enter a valid date.',
        'min'           => ':field must be at least :min characters.',
        'max'           => ':field cannot exceed :max characters.',
        'file_type'     => 'Please upload a JPG, PNG, WebP or PDF file.',
        'file_size'     => 'That file is too large. The maximum size is :size MB.',
        'file_required' => 'Please attach your receipt.',
        'generic'       => 'Please correct the highlighted fields.',
        'csrf'          => 'Your session expired for security reasons. Please try again.',
        'rate_limited'  => 'Too many attempts. Please wait a moment and try again.',
    ],

    // --- Errors -------------------------------------------------------
    'errors' => [
        '404_title'  => 'Page not found',
        '404_body'   => 'The page you are looking for does not exist or has been moved.',
        '403_title'  => 'Access denied',
        '403_body'   => 'You do not have permission to view this page.',
        '419_title'  => 'Session expired',
        '419_body'   => 'For your security your session expired. Please go back and try again.',
        '429_title'  => 'Too many requests',
        '429_body'   => 'Please wait a moment before trying again.',
        '500_title'  => 'Something went wrong',
        '500_body'   => 'We hit an unexpected problem. Our team has been notified. Please try again shortly.',
        'home'       => 'Return to homepage',
        'call_us'    => 'Or call us on :phone',
    ],

    // --- Footer -------------------------------------------------------
    'footer' => [
        'about'     => 'Professional, compassionate healthcare in :city. Modern facilities and expert clinical staff, designed around your wellbeing.',
        'explore'   => 'Explore',
        'contact'   => 'Contact',
        'legal'     => 'Legal',
        'privacy'   => 'Privacy policy',
        'terms'     => 'Terms of service',
        'rights'    => 'All rights reserved.',
        'staff'     => 'Staff login',
    ],

    // --- Email --------------------------------------------------------
    'email' => [
        'greeting'        => 'Hello :name,',
        'signoff'         => 'Warm regards,',
        'team'            => 'The MediCareMini team',
        'auto_note'       => 'This is an automated message. Please do not reply directly to this email.',
        'contact_note'    => 'Questions? Call us on :phone or reply to :email.',

        'booked_subject'  => 'Your appointment is booked - :ref',
        'booked_heading'  => 'Your appointment is booked',
        'booked_intro'    => 'Thank you for choosing MediCareMini. Here are your appointment details.',

        'reminder_subject' => 'Reminder: your appointment tomorrow - :ref',
        'reminder_heading' => 'Your appointment is tomorrow',
        'reminder_intro'   => 'This is a friendly reminder about your upcoming appointment.',
        'reminder_tips'    => 'Please arrive 10 minutes early and bring any previous test results or current medication.',

        'confirmed_subject' => 'Appointment confirmed - :ref',
        'confirmed_heading' => 'Your appointment is confirmed',
        'confirmed_intro'   => 'Our intake team has confirmed your appointment.',

        'cancelled_subject' => 'Appointment cancelled - :ref',
        'cancelled_heading' => 'Your appointment has been cancelled',
        'cancelled_intro'   => 'Your appointment has been cancelled. If this was not expected, please contact us.',

        'followup_subject'  => 'How are you feeling after your visit?',
        'followup_heading'  => 'Thank you for visiting us',
        'followup_intro'    => 'We hope you are feeling better. If you need a follow-up appointment or have questions about your results, we are here to help.',
        'followup_cta'      => 'Book a follow-up',

        'payment_received_subject' => 'We received your receipt - :ref',
        'payment_received_heading' => 'Receipt received',
        'payment_received_intro'   => 'Thank you. We have received your payment receipt and our finance team is reviewing it. You will hear from us within one working day.',

        'payment_verified_subject' => 'Payment confirmed - :ref',
        'payment_verified_heading' => 'Your payment is confirmed',
        'payment_verified_intro'   => 'We have verified your payment. Your appointment is fully secured.',

        'payment_rejected_subject' => 'Action needed on your payment - :ref',
        'payment_rejected_heading' => 'We could not verify your payment',
        'payment_rejected_intro'   => 'Unfortunately we could not verify the receipt you uploaded.',
        'payment_rejected_action'  => 'Please upload a corrected receipt using the link below, or call us so we can help.',

        'inquiry_subject'  => 'We received your message',
        'inquiry_heading'  => 'Thank you for contacting us',
        'inquiry_intro'    => 'We have received your message and a member of our team will respond shortly.',

        'label_reference' => 'Booking reference',
        'label_service'   => 'Service',
        'label_doctor'    => 'Doctor',
        'label_date'      => 'Date',
        'label_time'      => 'Time',
        'label_amount'    => 'Amount',
        'label_paid'      => 'Paid',
        'label_balance'   => 'Balance due',
        'label_status'    => 'Status',
        'label_location'  => 'Location',
        'label_reason'    => 'Reason',

        'view_booking'    => 'View my booking',
        'pay_now'         => 'Complete payment',
    ],

    // --- Misc ---------------------------------------------------------
    'common' => [
        'loading'   => 'Loading...',
        'from'      => 'From',
        'free'      => 'Free',
        'etb'       => 'ETB',
        'today'     => 'Today',
        'tomorrow'  => 'Tomorrow',
        'view_all'  => 'View all',
        'show_more' => 'Show more',
        'show_less' => 'Show less',
        'skip_to_content' => 'Skip to main content',
        'ethiopian_date'  => 'Ethiopian date',
    ],
];
