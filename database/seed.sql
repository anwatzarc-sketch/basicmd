-- =====================================================================
--  ASTER MEDICAL CENTER - SEED DATA
--  Run AFTER schema.sql.
--
--  NOTE: no `users` rows are seeded on purpose. Shipping a known
--  password hash is how demo installs get owned. Create the first
--  SuperAdmin with:   php bin/install.php
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------
--  Services (carried over from the prototype catalogue, now bilingual)
-- ---------------------------------------------------------------------
INSERT INTO `services`
  (`icon`,`name`,`name_am`,`slug`,`description`,`description_am`,`category`,`price`,`duration_min`,`is_featured`,`status`,`sort_order`)
VALUES
  ('🫀','Cardiology','የልብ ሕክምና','cardiology',
   'Heart and vascular assessment, prevention and ongoing care.',
   'የልብና የደም ቧንቧ ምርመራ፣ መከላከልና ተከታታይ ሕክምና።',
   'clinical', 1200.00, 30, 1, 'active', 10),

  ('🧒','Pediatrics','የሕፃናት ሕክምና','pediatrics',
   'Compassionate medical care for infants, children and adolescents.',
   'ለጨቅላ ሕፃናት፣ ለልጆችና ለታዳጊዎች ርኅሩኅ የሕክምና አገልግሎት።',
   'clinical', 800.00, 30, 1, 'active', 20),

  ('🩺','Internal Medicine','የውስጥ ደዌ ሕክምና','internal-medicine',
   'Comprehensive diagnosis and treatment for adult health conditions.',
   'ለአዋቂዎች የጤና ችግሮች የተሟላ ምርመራና ሕክምና።',
   'clinical', 900.00, 30, 1, 'active', 30),

  ('🌸','Gynecology','የማህፀንና ፅንስ ሕክምና','gynecology',
   'Women''s health, reproductive care and preventive services.',
   'የሴቶች ጤና፣ የስነ ተዋልዶ ክብካቤና የመከላከል አገልግሎቶች።',
   'clinical', 1000.00, 40, 0, 'active', 40),

  ('🦴','Orthopedics','የአጥንት ሕክምና','orthopedics',
   'Assessment and treatment for bones, joints and mobility.',
   'የአጥንት፣ የመገጣጠሚያና የእንቅስቃሴ ችግሮች ምርመራና ሕክምና።',
   'clinical', 1100.00, 30, 0, 'active', 50),

  ('🧴','Dermatology','የቆዳ ሕክምና','dermatology',
   'Clinical care for skin, hair and nail conditions.',
   'የቆዳ፣ የፀጉርና የጥፍር ሕመሞች ሕክምና።',
   'clinical', 850.00, 25, 0, 'active', 60),

  ('🧪','Laboratory','ላቦራቶሪ','laboratory',
   'Convenient diagnostic testing with reliable, fast results.',
   'አስተማማኝና ፈጣን ውጤት ያለው የላቦራቶሪ ምርመራ።',
   'diagnostics', 500.00, 20, 1, 'active', 70),

  ('🩻','Imaging & Radiology','ኢሜጂንግና ራዲዮሎጂ','imaging-radiology',
   'Modern digital X-ray and ultrasound imaging for accurate diagnosis.',
   'ለትክክለኛ ምርመራ ዘመናዊ ዲጂታል ኤክስሬይና አልትራሳውንድ።',
   'imaging', 1500.00, 30, 1, 'active', 80),

  ('💊','Pharmacy','ፋርማሲ','pharmacy',
   'Prescription medicines and professional medication guidance.',
   'የሐኪም ትዕዛዝ መድኃኒቶችና ሙያዊ የመድኃኒት አጠቃቀም ምክር።',
   'pharmacy', 0.00, 15, 0, 'active', 90),

  ('🚑','Emergency Care','የድንገተኛ ሕክምና','emergency-care',
   '24/7 emergency triage and urgent clinical response.',
   'የ24/7 የድንገተኛ አደጋ ምዘናና አፋጣኝ የሕክምና ምላሽ።',
   'emergency', 0.00, 60, 0, 'active', 100),

  ('🦷','Dental Care','የጥርስ ሕክምና','dental-care',
   'Routine dental checkups, cleaning and restorative treatment.',
   'መደበኛ የጥርስ ምርመራ፣ ጽዳትና የጥርስ ማደስ ሕክምና።',
   'clinical', 700.00, 40, 0, 'active', 110),

  ('🧠','Mental Health','የአእምሮ ጤና','mental-health',
   'Confidential counselling and psychiatric consultation.',
   'ሚስጥራዊ የምክር አገልግሎትና የአእምሮ ሕክምና ምክክር።',
   'wellness', 950.00, 50, 0, 'active', 120);

-- ---------------------------------------------------------------------
--  Doctors
-- ---------------------------------------------------------------------
INSERT INTO `doctors`
  (`full_name`,`full_name_am`,`slug`,`specialty`,`specialty_am`,`experience_years`,`credentials`,`bio`,`bio_am`,`initials`,`phone`,`daily_capacity`,`slot_capacity`,`consultation_fee`,`status`,`sort_order`)
VALUES
  ('Dr. Hana Tesfaye','ዶ/ር ሃና ተስፋዬ','dr-hana-tesfaye','Internal Medicine','የውስጥ ደዌ ሕክምና',12,
   'MD, Internal Medicine',
   'Dr. Hana leads our internal medicine team with a focus on chronic disease management, preventive screening and coordinated long-term care for adult patients.',
   'ዶ/ር ሃና የውስጥ ደዌ ሕክምና ቡድናችንን ትመራለች። ትኩረቷ በተላላፊ ያልሆኑ ሕመሞች አያያዝ፣ በመከላከል ምርመራና በአዋቂ ታካሚዎች ተከታታይ ክብካቤ ላይ ነው።',
   'HT','+251911234567',16,4,900.00,'active',10),

  ('Dr. Dawit Bekele','ዶ/ር ዳዊት በቀለ','dr-dawit-bekele','Cardiology','የልብ ሕክምና',10,
   'MD, FACC - Cardiology',
   'Dr. Dawit is a board-certified cardiologist specialising in hypertension, heart failure management and non-invasive cardiac diagnostics including ECG and echocardiography.',
   'ዶ/ር ዳዊት የተመሰከረለት የልብ ሕክምና ባለሙያ ሲሆን በደም ግፊት፣ በልብ ድካም አያያዝና ECG እና ኢኮካርዲዮግራፊን ጨምሮ በልብ ምርመራዎች ላይ ይሰራል።',
   'DB','+251911345678',12,3,1200.00,'active',20),

  ('Dr. Sara Alemu','ዶ/ር ሳራ ዓለሙ','dr-sara-alemu','Pediatrics','የሕፃናት ሕክምና',9,
   'MD, Pediatrics',
   'Dr. Sara provides comprehensive care for newborns through adolescence, including growth monitoring, immunisation scheduling and acute childhood illness.',
   'ዶ/ር ሳራ ከጨቅላነት እስከ ታዳጊነት ድረስ የተሟላ ክብካቤ ትሰጣለች፤ የዕድገት ክትትል፣ የክትባት መርሃ ግብርና የሕፃናት ድንገተኛ ሕመሞችን ትሸፍናለች።',
   'SA','+251911456789',18,5,800.00,'active',30),

  ('Dr. Michael Getachew','ዶ/ር ሚካኤል ጌታቸው','dr-michael-getachew','Orthopedics','የአጥንት ሕክምና',11,
   'MD, Orthopedic Surgery',
   'Dr. Michael treats musculoskeletal injuries, sports-related trauma and degenerative joint conditions, with an emphasis on conservative management before surgery.',
   'ዶ/ር ሚካኤል የጡንቻና አጥንት ጉዳቶችን፣ ከስፖርት ጋር የተያያዙ አደጋዎችንና የመገጣጠሚያ እርጅና ሕመሞችን ያክማል፤ ከቀዶ ጥገና በፊት በወግ አጥባቂ ሕክምና ላይ ያተኩራል።',
   'MG','+251911567890',10,3,1100.00,'active',40),

  ('Dr. Meaza Girma','ዶ/ር መዓዛ ግርማ','dr-meaza-girma','Gynecology','የማህፀንና ፅንስ ሕክምና',14,
   'MD, OB/GYN',
   'Dr. Meaza offers antenatal care, family planning counselling and management of gynecological conditions in a private, respectful setting.',
   'ዶ/ር መዓዛ የቅድመ ወሊድ ክብካቤ፣ የቤተሰብ ምጣኔ ምክርና የማህፀን ሕመሞች ሕክምናን በሚስጥራዊና በአክብሮት ሁኔታ ትሰጣለች።',
   'MG','+251911678901',14,4,1000.00,'active',50),

  ('Dr. Yonas Haile','ዶ/ር ዮናስ ኃይሌ','dr-yonas-haile','Dermatology','የቆዳ ሕክምና',7,
   'MD, Dermatology',
   'Dr. Yonas manages acne, eczema, pigmentation disorders and skin infections, combining clinical treatment with practical daily skincare guidance.',
   'ዶ/ር ዮናስ ብጉር፣ ኤክዚማ፣ የቆዳ ቀለም መዛባትና የቆዳ ኢንፌክሽኖችን ያክማል፤ ከሕክምና ጎን ለጎን ተግባራዊ የዕለት ተዕለት የቆዳ እንክብካቤ ምክር ይሰጣል።',
   'YH','+251911789012',16,4,850.00,'active',60);

-- ---------------------------------------------------------------------
--  Facilities
-- ---------------------------------------------------------------------
INSERT INTO `facilities`
  (`name`,`name_am`,`type`,`room_label`,`description`,`description_am`,`status`,`notes`,`is_public`,`sort_order`)
VALUES
  ('Modern Consultation Suites','ዘመናዊ የምክክር ክፍሎች','Clinical Room','Room 102-108',
   'Private, comfortable spaces designed for meaningful conversations and complete patient confidentiality.',
   'ትርጉም ያለው ውይይትና ሙሉ የታካሚ ሚስጥራዊነት እንዲኖር ተብለው የተዘጋጁ ምቹና የግል ክፍሎች።',
   'operational','Fully equipped for internal medicine and specialist clinics.',1,10),

  ('Diagnostic Imaging Wing','የኢሜጂንግ ክፍል','Radiology','Ground Floor B',
   'Digital X-ray and ultrasound access with a streamlined patient journey for fast result turnaround.',
   'ዲጂታል ኤክስሬይና አልትራሳውንድ አገልግሎት፤ ውጤት በፍጥነት እንዲደርስ የተቀላጠፈ የታካሚ ሂደት።',
   'operational','X-ray calibration completed this quarter.',1,20),

  ('Clinical Pathology Laboratory','የክሊኒካል ፓቶሎጂ ላቦራቶሪ','Laboratory','Level 2',
   'Full hematology, biochemistry and microbiology panels processed on-site for same-day results.',
   'ሙሉ የደም፣ የባዮኬሚስትሪና የማይክሮባዮሎጂ ምርመራዎች በቦታው ተሰርተው በዕለቱ ውጤት ይሰጣል።',
   'operational','Centrifuge unit serviced and returned to full capacity.',1,30),

  ('Emergency Response Room','የድንገተኛ አደጋ ክፍል','Urgent Care','Main Gate Suite',
   'Round-the-clock triage bay staffed by emergency-trained clinicians.',
   'በድንገተኛ አደጋ ሕክምና የሰለጠኑ ባለሙያዎች የሚያገለግሉበት የ24 ሰዓት የምዘና ክፍል።',
   'operational','24/7 triage ready.',1,40),

  ('On-site Pharmacy','የውስጥ ፋርማሲ','Pharmacy','Reception Level',
   'Dispensing pharmacy stocked with essential and specialist medications.',
   'መሰረታዊና ልዩ መድኃኒቶችን የያዘ የመድኃኒት መሸጫ።',
   'operational','Stock reconciliation weekly.',1,50);

-- ---------------------------------------------------------------------
--  Health packages - the prepaid screening revenue engine.
--  deposit_rate 0.300 = 30% held up front to secure the slot.
-- ---------------------------------------------------------------------
INSERT INTO `health_packages`
  (`title`,`title_am`,`slug`,`price_etb`,`deposit_rate`,`description`,`description_am`,`items_json`,`badge`,`is_featured`,`status`,`sort_order`)
VALUES
  ('Essential Check','መሰረታዊ ምርመራ','essential-check',2500.00,0.300,
   'Basic preventive screening and core laboratory battery - ideal as an annual baseline for healthy adults.',
   'መሰረታዊ የመከላከል ምርመራና ዋና ዋና የላቦራቶሪ ምርመራዎች - ለጤናማ አዋቂዎች ዓመታዊ መነሻ ምርመራ ተስማሚ።',
   JSON_OBJECT(
     'en', JSON_ARRAY(
       'General physician consultation',
       'Complete blood count (CBC)',
       'Fasting blood sugar',
       'Lipid profile (cholesterol)',
       'Blood pressure & BMI assessment',
       'Urinalysis',
       'Written results summary within 48 hours'
     ),
     'am', JSON_ARRAY(
       'የጠቅላላ ሐኪም ምክክር',
       'ሙሉ የደም ምርመራ (CBC)',
       'የጾም የደም ስኳር',
       'የስብ መጠን ምርመራ (ኮሌስትሮል)',
       'የደም ግፊትና የሰውነት ክብደት ምዘና',
       'የሽንት ምርመራ',
       'በ48 ሰዓት ውስጥ የተጻፈ የውጤት ማጠቃለያ'
     )
   ),
   'Most popular',1,'active',10),

  ('Executive Health','የአስፈጻሚ ጤና ምርመራ','executive-health',6500.00,0.300,
   'Comprehensive annual screening with specialist consultation, cardiac workup and imaging - built for demanding schedules.',
   'ከልዩ ባለሙያ ምክክር፣ ከልብ ምርመራና ከኢሜጂንግ ጋር የተሟላ ዓመታዊ ምርመራ - ለተጨናነቀ የሥራ መርሃ ግብር የተዘጋጀ።',
   JSON_OBJECT(
     'en', JSON_ARRAY(
       'Everything in Essential Check',
       'Specialist physician consultation',
       'Resting ECG & cardiac risk assessment',
       'Abdominal ultrasound',
       'Chest X-ray',
       'Liver & kidney function panel',
       'Thyroid function screening',
       'Priority scheduling & dedicated coordinator',
       'Detailed report with specialist review'
     ),
     'am', JSON_ARRAY(
       'በመሰረታዊ ምርመራ ውስጥ ያሉት ሁሉም',
       'የልዩ ባለሙያ ሐኪም ምክክር',
       'የዕረፍት ጊዜ ECG እና የልብ አደጋ ምዘና',
       'የሆድ አልትራሳውንድ',
       'የደረት ኤክስሬይ',
       'የጉበትና የኩላሊት ሥራ ምርመራ',
       'የታይሮይድ ሥራ ምርመራ',
       'ቅድሚያ የሚሰጠው ቀጠሮና ልዩ አስተባባሪ',
       'በልዩ ባለሙያ የተገመገመ ዝርዝር ሪፖርት'
     )
   ),
   'Comprehensive',1,'active',20),

  ('Women''s Wellness','የሴቶች ጤንነት ምርመራ','womens-wellness',4200.00,0.300,
   'Preventive screening designed around women''s health priorities across all life stages.',
   'በሁሉም የሕይወት ደረጃዎች የሴቶችን የጤና ቅድሚያዎች ታሳቢ ያደረገ የመከላከል ምርመራ።',
   JSON_OBJECT(
     'en', JSON_ARRAY(
       'Gynecological consultation',
       'Complete blood count & iron studies',
       'Pelvic ultrasound',
       'Breast clinical examination',
       'Cervical screening',
       'Bone health & vitamin D assessment'
     ),
     'am', JSON_ARRAY(
       'የማህፀን ሕክምና ምክክር',
       'ሙሉ የደም ምርመራና የብረት መጠን ምርመራ',
       'የማህፀን አካባቢ አልትራሳውንድ',
       'የጡት ክሊኒካዊ ምርመራ',
       'የማህፀን በር ጫፍ ምርመራ',
       'የአጥንት ጤናና የቫይታሚን D ምዘና'
     )
   ),
   NULL,0,'active',30);

-- ---------------------------------------------------------------------
--  Payment methods - the manual-transfer instructions shown at checkout.
--  REPLACE the account numbers below with the clinic's real accounts
--  before going live.
-- ---------------------------------------------------------------------
INSERT INTO `payment_methods`
  (`channel`,`provider`,`provider_am`,`account_name`,`account_number`,`branch`,`instructions`,`instructions_am`,`requires_proof`,`status`,`sort_order`)
VALUES
  ('bank_transfer','Commercial Bank of Ethiopia','የኢትዮጵያ ንግድ ባንክ',
   'Aster Medical Center PLC','1000XXXXXXXXX','Bole Branch, Addis Ababa',
   'Transfer the amount to the CBE account above, then upload a clear photo of the deposit slip. Write your booking reference in the transfer reason field so our finance team can match it quickly.',
   'ከላይ ወደተጠቀሰው የኢትዮጵያ ንግድ ባንክ ሒሳብ ገንዘቡን ያስተላልፉ፤ ከዚያም የደረሰኙን ግልጽ ፎቶ ይጫኑ። የፋይናንስ ቡድናችን በቀላሉ እንዲያገናኘው የቀጠሮ መለያዎን በማስተላለፊያው ምክንያት ሳጥን ውስጥ ይጻፉ።',
   1,'active',10),

  ('mobile_money','Telebirr','ቴሌብር',
   'Aster Medical Center','+251911123456',NULL,
   'Send the amount via Telebirr to the merchant number above. After the transaction completes, upload the Telebirr confirmation screenshot showing the transaction ID.',
   'ከላይ ወደተጠቀሰው የነጋዴ ቁጥር በቴሌብር ገንዘቡን ይላኩ። ክፍያው ከተጠናቀቀ በኋላ የግብይት መለያውን የሚያሳየውን የቴሌብር ማረጋገጫ ስክሪንሾት ይጫኑ።',
   1,'active',20),

  ('bank_transfer','Dashen Bank','ዳሽን ባንክ',
   'Aster Medical Center PLC','0000XXXXXXXXX','Bole Medhanialem Branch',
   'Transfer to the Dashen account above and upload the bank slip. Mobile-banking screenshots are accepted as long as the transaction reference and amount are legible.',
   'ከላይ ወደተጠቀሰው የዳሽን ባንክ ሒሳብ አስተላልፈው የባንክ ደረሰኙን ይጫኑ። የግብይት መለያውና መጠኑ በግልጽ እስከታየ ድረስ የሞባይል ባንኪንግ ስክሪንሾት ተቀባይነት አለው።',
   1,'active',30),

  ('cash_on_arrival','Pay at Reception','በአቀባበል ክፍል መክፈል',
   NULL,NULL,NULL,
   'Reserve your slot now and settle the full amount in cash or by card at our reception desk when you arrive. Please arrive 15 minutes early to complete payment before your appointment time.',
   'አሁን ቀጠሮዎን ይያዙ፤ ሲደርሱም ሙሉ ክፍያውን በጥሬ ገንዘብ ወይም በካርድ በአቀባበል ክፍላችን ይፈጽሙ። ከቀጠሮዎ በፊት ክፍያውን ለመፈጸም እባክዎ 15 ደቂቃ ቀደም ብለው ይምጡ።',
   0,'active',40);

-- ---------------------------------------------------------------------
--  Articles - the SEO Knowledge Hub seed set.
--  author_id / reviewer_id reference `doctors`.
-- ---------------------------------------------------------------------
INSERT INTO `articles`
  (`title`,`title_am`,`slug`,`category`,`excerpt`,`excerpt_am`,`content`,`content_am`,`author_id`,`reviewer_id`,`schema_type`,`meta_title`,`meta_description`,`focus_keyword`,`read_minutes`,`views`,`status`,`published_at`)
VALUES
  ('5 Habits That Support Heart Health','የልብ ጤናን የሚደግፉ 5 ልማዶች','5-habits-that-support-heart-health','Prevention',
   'Simple daily choices can make a meaningful difference to long-term cardiovascular wellbeing.',
   'ቀላል የዕለት ተዕለት ምርጫዎች ለረጅም ጊዜ የልብና የደም ቧንቧ ጤና ትልቅ ለውጥ ያመጣሉ።',
   '<p>Cardiovascular disease is rising across urban Ethiopia, and Addis Ababa is no exception. The encouraging news is that most of the risk is modifiable through habits you control every day.</p><h2>1. Move for 30 minutes, five days a week</h2><p>Brisk walking counts. Consistency matters more than intensity, and a daily walk around your neighbourhood is enough to lower resting blood pressure over several weeks.</p><h2>2. Reduce added salt</h2><p>High sodium intake is one of the strongest drivers of hypertension. Cook with herbs and spices, and taste food before reaching for the salt shaker.</p><h2>3. Do not smoke, and avoid second-hand smoke</h2><p>Smoking damages the lining of your arteries within minutes. Quitting lowers heart-attack risk measurably within the first year.</p><h2>4. Sleep seven to eight hours</h2><p>Chronic short sleep raises blood pressure and disrupts glucose control. Keep a consistent bedtime, even on weekends.</p><h2>5. Know your numbers</h2><p>Blood pressure, cholesterol and blood sugar are silent until they are not. An annual screening catches problems while they are still easy to treat.</p><p><strong>When to see a doctor:</strong> chest pain, breathlessness on mild exertion, palpitations or swelling in the ankles warrant prompt assessment.</p>',
   '<p>የልብና የደም ቧንቧ ሕመም በኢትዮጵያ ከተሞች እየጨመረ ነው፤ አዲስ አበባም ከዚህ የተለየች አይደለችም። የሚያበረታታው ነገር አብዛኛው አደጋ በየዕለቱ በሚቆጣጠሩት ልማዶች ሊቀየር መቻሉ ነው።</p><h2>1. በሳምንት አምስት ቀን ለ30 ደቂቃ ይንቀሳቀሱ</h2><p>ፈጣን የእግር ጉዞ ይበቃል። ከጥንካሬው ይልቅ ቀጣይነቱ ይበልጣል፤ በአካባቢዎ የሚያደርጉት ዕለታዊ የእግር ጉዞ በጥቂት ሳምንታት ውስጥ የደም ግፊትዎን ይቀንሳል።</p><h2>2. የተጨመረ ጨውን ይቀንሱ</h2><p>ከፍተኛ የጨው መጠን ለደም ግፊት ዋነኛ መንስኤ ነው። በቅመማ ቅመም ያብስሉ፤ ጨው ከመጨመርዎ በፊት ምግቡን ይቅመሱ።</p><h2>3. ሲጋራ አያጨሱ፤ ከጭስም ይራቁ</h2><p>ማጨስ በደቂቃዎች ውስጥ የደም ቧንቧዎችዎን ውስጠኛ ክፍል ይጎዳል። ማቆም በመጀመሪያው ዓመት ውስጥ የልብ ድካም አደጋን በሚታይ ሁኔታ ይቀንሳል።</p><h2>4. ከሰባት እስከ ስምንት ሰዓት ይተኙ</h2><p>ተደጋጋሚ የእንቅልፍ እጥረት የደም ግፊትን ከፍ ያደርጋል፤ የስኳር ቁጥጥርንም ያዛባል። በሳምንቱ መጨረሻም ቢሆን ወጥ የሆነ የመኝታ ሰዓት ይያዙ።</p><h2>5. ቁጥሮችዎን ይወቁ</h2><p>የደም ግፊት፣ ኮሌስትሮልና የደም ስኳር ምልክት ሳያሳዩ ይቆያሉ። ዓመታዊ ምርመራ ችግሮችን በቀላሉ በሚታከሙበት ጊዜ ይይዛቸዋል።</p><p><strong>ሐኪም መቼ ማየት እንዳለብዎ፡</strong> የደረት ሕመም፣ በቀላል እንቅስቃሴ መተንፈስ መቸገር፣ የልብ ምት መዛባት ወይም የቁርጭምጭሚት እብጠት ካለ በፍጥነት ምርመራ ያስፈልጋል።</p>',
   2,1,'MedicalWebPage',
   'Heart Health Tips from Cardiologists in Addis Ababa | Aster Medical',
   'Five evidence-based habits that protect your heart, explained by the cardiology team at Aster Medical Center in Bole, Addis Ababa.',
   'heart health addis ababa',5,482,'published','2026-02-10 09:00:00'),

  ('When Should Your Child See a Pediatrician?','ልጅዎን ወደ ሕፃናት ሐኪም መቼ ማምጣት አለብዎ?','when-should-your-child-see-a-pediatrician','Family Health',
   'A practical guide to common pediatric symptoms and when professional assessment is recommended.',
   'ስለተለመዱ የሕፃናት ሕመም ምልክቶችና ሙያዊ ምርመራ መቼ እንደሚያስፈልግ ተግባራዊ መመሪያ።',
   '<p>Most childhood illnesses resolve on their own. Knowing which symptoms need a clinician saves you an unnecessary trip - and, more importantly, makes sure you do not delay one that matters.</p><h2>Seek care the same day</h2><ul><li>Fever above 38&deg;C in an infant under three months</li><li>Difficulty breathing, rapid breathing, or visible chest retraction</li><li>Persistent vomiting preventing fluid intake</li><li>Signs of dehydration: dry mouth, no tears, reduced wet nappies</li><li>A rash that does not fade when pressed</li><li>Unusual drowsiness or difficulty waking</li></ul><h2>Book a routine appointment</h2><ul><li>Cough lasting more than two weeks</li><li>Recurring ear pain</li><li>Poor weight gain or appetite change over weeks</li><li>Concerns about developmental milestones</li></ul><h2>Well-child visits still matter</h2><p>Even a healthy child benefits from scheduled checkups. They keep immunisations on track and catch growth or hearing issues before they affect schooling.</p>',
   '<p>አብዛኞቹ የሕፃናት ሕመሞች በራሳቸው ይድናሉ። የትኞቹ ምልክቶች ሐኪም እንደሚያስፈልጋቸው ማወቅ አላስፈላጊ ጉዞን ያስቀርልዎታል፤ ከዚያም በላይ አስፈላጊ የሆነውን ጉብኝት እንዳያዘገዩ ያደርጋል።</p><h2>በዚያኑ ዕለት ሕክምና ይፈልጉ</h2><ul><li>ከሦስት ወር በታች በሆነ ሕፃን ከ38 ዲግሪ ሴልሺየስ በላይ ትኩሳት</li><li>የመተንፈስ ችግር፣ ፈጣን ትንፋሽ ወይም የደረት መጎተት</li><li>ፈሳሽ እንዳይወስድ የሚያግድ ተደጋጋሚ ትውከት</li><li>የውሃ እጥረት ምልክቶች፡ የደረቀ አፍ፣ እንባ አለመኖር፣ የሽንት መቀነስ</li><li>ሲጫኑት የማይጠፋ ሽፍታ</li><li>ያልተለመደ እንቅልፍ ወይም ለመንቃት መቸገር</li></ul><h2>መደበኛ ቀጠሮ ይያዙ</h2><ul><li>ከሁለት ሳምንት በላይ የቆየ ሳል</li><li>ተደጋጋሚ የጆሮ ሕመም</li><li>የክብደት አለመጨመር ወይም የምግብ ፍላጎት መቀነስ</li><li>ስለ ዕድገት ደረጃዎች ስጋት ካለ</li></ul><h2>መደበኛ የጤና ክትትል አሁንም ያስፈልጋል</h2><p>ጤናማ ልጅም ቢሆን ከመደበኛ ምርመራ ይጠቀማል። ክትባቶች በሰዓቱ እንዲሰጡ ያደርጋል፤ የዕድገትና የመስማት ችግሮችንም ትምህርት ላይ ተጽዕኖ ከማሳደራቸው በፊት ይይዛል።</p>',
   3,3,'MedicalWebPage',
   'When to Take Your Child to a Pediatrician | Aster Medical Center',
   'Clear guidance from Addis Ababa pediatricians on the childhood symptoms that need same-day care versus a routine appointment.',
   'pediatrician addis ababa',4,310,'published','2026-02-18 09:00:00'),

  ('Why Routine Health Checks Matter','መደበኛ የጤና ምርመራ ለምን ያስፈልጋል?','why-routine-health-checks-matter','Wellness',
   'Preventive checkups identify risk factors early, before they become larger and more expensive health problems.',
   'የመከላከል ምርመራዎች አደጋዎችን ትልቅና ውድ ችግር ከመሆናቸው በፊት ቀድመው ይለያሉ።',
   '<p>Hypertension, diabetes and high cholesterol share an inconvenient trait: they cause no symptoms until damage is already done. A screening appointment is the only reliable way to find them early.</p><h2>What a screening actually covers</h2><p>A baseline check measures blood pressure, fasting glucose, a lipid panel and kidney function, alongside a physical examination and a conversation about family history and lifestyle.</p><h2>How often?</h2><p>Healthy adults under 40 benefit from a check every two years. From 40 onwards - or earlier with a family history of heart disease or diabetes - annual screening is the sensible default.</p><h2>The economics of early detection</h2><p>Treating established complications costs many times more than managing a risk factor caught early. Prevention is the cheapest medicine available.</p>',
   '<p>የደም ግፊት፣ የስኳር ሕመምና ከፍተኛ ኮሌስትሮል አንድ የሚያመሳስላቸው ነገር አለ፡ ጉዳቱ እስኪደርስ ድረስ ምንም ምልክት አያሳዩም። እነሱን ቀድሞ ለማግኘት ብቸኛው አስተማማኝ መንገድ የምርመራ ቀጠሮ ነው።</p><h2>ምርመራው ምን ይሸፍናል</h2><p>መሰረታዊ ምርመራ የደም ግፊትን፣ የጾም የደም ስኳርን፣ የስብ መጠንንና የኩላሊት ሥራን ይለካል፤ ከአካላዊ ምርመራና ስለ ቤተሰብ ታሪክና የአኗኗር ዘይቤ ከሚደረግ ውይይት ጋር።</p><h2>በምን ያህል ጊዜ?</h2><p>ከ40 ዓመት በታች ያሉ ጤናማ አዋቂዎች በየሁለት ዓመቱ ምርመራ ማድረግ ይጠቅማቸዋል። ከ40 ዓመት በኋላ - ወይም የልብ ሕመም ወይም የስኳር የቤተሰብ ታሪክ ካለ ቀደም ብሎ - ዓመታዊ ምርመራ ተገቢ ነው።</p><h2>ቀድሞ የማወቅ ኢኮኖሚያዊ ጥቅም</h2><p>የተከሰቱ ችግሮችን ማከም ቀድሞ የተያዘን የአደጋ ምክንያት ከመቆጣጠር ብዙ እጥፍ ያስወጣል። መከላከል በጣም ርካሹ መድኃኒት ነው።</p>',
   1,2,'MedicalWebPage',
   'Annual Health Screening in Addis Ababa | Aster Medical Center',
   'Why preventive health screening saves money and lives, and how often adults in Addis Ababa should book a checkup.',
   'health screening addis ababa',4,198,'published','2026-03-01 09:00:00'),

  ('Managing High Blood Pressure in Daily Life','የደም ግፊትን በዕለት ተዕለት ሕይወት ማስተዳደር','managing-high-blood-pressure-daily-life','Prevention',
   'Practical, locally realistic steps for keeping hypertension under control between clinic visits.',
   'በክሊኒክ ጉብኝቶች መካከል የደም ግፊትን ለመቆጣጠር ተግባራዊና ከአካባቢው ሁኔታ ጋር የሚጣጣሙ እርምጃዎች።',
   '<p>A hypertension diagnosis is not a crisis - it is information. What you do with it over the following months determines your long-term risk far more than the number on the day of diagnosis.</p><h2>Take medication as prescribed</h2><p>Blood-pressure medication works only while it is in your system. Stopping because you feel fine is the single most common reason control is lost.</p><h2>Watch the salt you cannot see</h2><p>Processed foods, bouillon cubes and preserved meats carry more sodium than the salt shaker does. Read labels where they exist, and cook fresh where you can.</p><h2>Measure at home</h2><p>A home monitor used twice weekly, at the same time of day, gives your doctor far better data than a single clinic reading taken when you are rushed and anxious.</p><h2>Bring your readings to every appointment</h2><p>A written log lets your clinician adjust treatment on evidence rather than guesswork.</p>',
   '<p>የደም ግፊት እንዳለብዎ መታወቁ ቀውስ አይደለም - መረጃ ነው። በቀጣዮቹ ወራት በዚህ መረጃ የሚያደርጉት ነገር ከምርመራው ዕለት ቁጥር ይልቅ የረጅም ጊዜ አደጋዎን ይወስናል።</p><h2>መድኃኒትዎን በታዘዘው መሠረት ይውሰዱ</h2><p>የደም ግፊት መድኃኒት የሚሰራው በሰውነትዎ ውስጥ እስካለ ድረስ ብቻ ነው። ጥሩ ስለተሰማዎት ማቆም ቁጥጥር የሚጠፋበት ዋነኛ ምክንያት ነው።</p><h2>የማይታየውን ጨው ይጠንቀቁ</h2><p>የተዘጋጁ ምግቦች፣ የሾርባ ኩቦችና የተቀመሙ ሥጋዎች ከጨው ማንኪያ በላይ ሶዲየም ይይዛሉ። ባሉበት ቦታ መለያዎችን ያንብቡ፤ በተቻለ መጠንም ትኩስ ምግብ ያብስሉ።</p><h2>በቤት ውስጥ ይለኩ</h2><p>በሳምንት ሁለት ጊዜ በተመሳሳይ ሰዓት የሚጠቀሙት የቤት መለኪያ፣ ተጣድፈውና ተጨንቀው በክሊኒክ ከሚወሰድ አንድ ንባብ ይልቅ ለሐኪምዎ በጣም የተሻለ መረጃ ይሰጣል።</p><h2>ንባቦችዎን ወደ እያንዳንዱ ቀጠሮ ይዘው ይምጡ</h2><p>የተጻፈ መዝገብ ሐኪምዎ በግምት ሳይሆን በማስረጃ ላይ ተመስርቶ ሕክምናዎን እንዲያስተካክል ያስችለዋል።</p>',
   2,1,'MedicalCondition',
   'Managing High Blood Pressure | Hypertension Care in Addis Ababa',
   'How to keep hypertension controlled day to day, from the cardiology team at Aster Medical Center, Bole, Addis Ababa.',
   'high blood pressure treatment ethiopia',5,0,'draft',NULL);

-- ---------------------------------------------------------------------
--  System settings - runtime configuration editable by SuperAdmin.
--  `is_public = 1` marks values safe to render in public templates.
-- ---------------------------------------------------------------------
INSERT INTO `system_settings` (`setting_key`,`value`,`group_name`,`value_type`,`label`,`is_public`) VALUES
  ('clinic_name','Aster Medical Center','general','string','Clinic name',1),
  ('clinic_name_am','አስቴር ሕክምና ማዕከል','general','string','Clinic name (Amharic)',1),
  ('tagline','Exceptional healthcare. Designed around you.','general','string','Tagline',1),
  ('tagline_am','ልዩ የሕክምና አገልግሎት። ለእርስዎ ተዘጋጅቶ የቀረበ።','general','string','Tagline (Amharic)',1),
  ('address','Bole Sub-city, Addis Ababa, Ethiopia','contact','string','Street address',1),
  ('address_am','ቦሌ ክፍለ ከተማ፣ አዲስ አበባ፣ ኢትዮጵያ','contact','string','Street address (Amharic)',1),
  ('phone_primary','+251911123456','contact','string','Primary phone',1),
  ('phone_secondary','+251116000000','contact','string','Secondary phone',1),
  ('phone_emergency','+251911999000','contact','string','Emergency hotline',1),
  ('email_public','info@pyramid.biz.et','contact','string','Public email',1),
  ('email_admin_notify','frontdesk@pyramid.biz.et','notifications','string','Internal notification inbox',0),
  ('operating_hours','Mon-Sat: 08:00 - 18:00 | Emergency 24/7','contact','string','Operating hours',1),
  ('operating_hours_am','ሰኞ-ቅዳሜ፡ 08:00 - 18:00 | ድንገተኛ አደጋ 24/7','contact','string','Operating hours (Amharic)',1),
  ('map_latitude','8.9969','location','string','Map latitude',1),
  ('map_longitude','38.7869','location','string','Map longitude',1),
  ('map_zoom','16','location','int','Map zoom level',1),
  ('default_locale','en','localization','string','Default site language',1),
  ('show_ethiopian_calendar','1','localization','bool','Show Ethiopian calendar dates',1),
  ('express_surcharge_rate','0.20','booking','string','Express queue surcharge rate',0),
  ('booking_lead_hours','12','booking','int','Minimum hours before a bookable slot',0),
  ('booking_horizon_days','60','booking','int','How far ahead patients may book',0),
  ('booking_closed_weekdays','0','booking','json','Weekdays closed (0=Sunday)',0),
  ('reminder_lead_hours','24','notifications','int','Hours before appointment to send reminder',0),
  ('followup_delay_hours','48','notifications','int','Hours after visit to send follow-up',0),
  ('proof_max_mb','5','uploads','int','Max payment proof size (MB)',0),
  ('facebook_url','','social','string','Facebook URL',1),
  ('telegram_url','','social','string','Telegram URL',1),
  ('instagram_url','','social','string','Instagram URL',1),
  ('linkedin_url','','social','string','LinkedIn URL',1);
