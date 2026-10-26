# التسجيل لأول مرة

## السلوك المنفذ
- `/register`: معلومات شخصية ومعلومات الحساب، رقم جوال محلي يبدأ بـ7 من 9 أرقام ورمز +967 منفصل، تأكيد الهاتف، البريد واسم المستخدم وكلمة المرور، شروط غير محددة افتراضيًا، CAPTCHA خادمية عشوائية. زر «تحقق من الرمز» يفحص الصورة في الخادم قبل تفعيل «التالي»؛ الإرسال النهائي يعيد التحقق مستقلًا ولا يثق بعلم JavaScript.
- `/register/verify`: لا تظهر إلا لمحاولة أرسلت لها خدمة SMS رمزًا فعليًا. الرمز 6 أرقام، صالح 10 دقائق، 5 محاولات تحقق إجمالًا؛ إعادة الإرسال لا تصفر عدد المحاولات ولا تمدد انتهاء الجلسة. 60 ثانية بين الإرسال، 3 إرساليات ناجحة للمحاولة، 5 محاولات إرسال للرقم خلال الساعة، وحدود إضافية للمسارات حسب IP.
- `/register/complete`: يتطلب حسابًا أنشئ بعد تحقق الرمز وجلسة الاكتمال نفسها. لا يعرض نجاحًا من خطوة متصفح مستقلة.
- تُشفّر بيانات محاولة التسجيل في قاعدة البيانات، وكلمة المرور داخلها مجزأة والرمز مجزأ. مفاتيح حدود الإرسال HMAC للهاتف، وقفل Cache ذري للهاتف، وقفل صف المحاولة أثناء التحقق. لا تُسجل استجابات المزود أو الرمز، ولا تدخل كلمة المرور أو OTP في old_input أو localStorage.
- تصحيح البيانات يلغي المحاولة والرمز القديم ويحتفظ ببيانات النموذج غير الحساسة في الجلسة فقط. قد تؤخر حدود الإرسال استخدام الرقم نفسه مجددًا.
- رقم الهوية `national_id` منفصل عن `username` والهاتف. الحسابات القديمة تظل تستخدم username القديم كمعرّفها المسجل، دون إعادة تسميتها أو إعلان توثيقها.
- `no_identity` ينشئ `initial_account`؛ يسمح فقط بإجراءات ومرفقات `identity-first-issue` مع بقاء شروط الخدمة والإثبات الموثق. `existing_record` و`has_identity` ينشئان `identity_verification_pending`. الهاتف المؤكد لا يعتمد الهوية أو البريد. is_active حالة إتاحة الحساب، وليست اعتماد الهوية المدنية.
- للمسؤول المخوّل تقييم الحساب الأولي بمعرّفه الداخلي بدل اختلاق رقم وطني، في خدمة إصدار البطاقة لأول مرة فقط. يظل مرجع الإثبات وباقي الفحوص إلزاميًا. لا يغيّر التقييم حالة الحساب العامة أو يعتمد رقم هوية تلقائيًا.
- تغيير الهاتف في الملف الشخصي يزيل phone_verified_at. لا يستطيع المواطن تعديل الهوية أو حالات الاعتماد.

## SMS والتكاملات المطلوبة
**لا يوجد مزود SMS مربوط حاليًا.** `UnavailableSms` يرفض الإرسال برسالة واضحة، ولا ينشئ حسابًا، ولا يوجد رمز تجريبي ثابت أو تجاوز للتحقق. الاختبارات وحدها تربط مزودًا في الذاكرة؛ لم ترسل الاختبارات رسائل حقيقية.

للتفعيل يلزم عقد API لمزود مخوّل بإرسال الرسائل إلى اليمن، واسم المرسل وسياسة الأخطاء ومهلات الإرسال. نفّذ adapter لواجهة `App\Contracts\RegistrationSms` واربطه بدل `UnavailableSms` في `AppServiceProvider`. يجب أن تعود `send()` بنجاح فقط بعد قبول المزود الحقيقي للرسالة، وأن ترمي استثناء عند الفشل أو الشك، دون طباعة الرمز أو الهاتف في السجلات. استخدم مهلة اتصال/طلب قصيرة (أقل من 10 ثوان)، ولا تعد الإرسال آليًا بعد نتيجة ملتبسة. استخدم idempotency عند دعم المزود، ولا تفترض اسم حقول أو عقد HTTP غير متوفر.

لا توجد متغيرات بيئة SMS جديدة وهمية: يحدد adapter أسماء متغيراته بعد اختيار المزود، وتبقى الأسرار محليًا في `.env` أو مخزن أسرار، ولا تُحفظ في Git أو الوثائق. الإعدادات الحالية في `config/registration.php` (المدد والحدود)، وتعتمد على APP_KEY الموجود وتخزين جلسات وCache خادمي مشترك يدعم الأقفال؛ لا تستخدم array cache في الإنتاج. بيئة المشروع المحلية تستخدم Cache قاعدة البيانات.

CAPTCHA الحالية تحقق خادمي عشوائي أساسي بصلاحية 5 دقائق، لكنها ليست خدمة متقدمة لمقاومة الروبوتات/OCR، ويلزم تعزيزها قبل الإطلاق العام. صفحة الشروط **مسودة أكاديمية** تحتاج مراجعة واعتماد سياسة الخصوصية والشروط النهائية. لا يوجد تكامل سجل مدني حقيقي لاعتماد هوية عامة للحساب؛ لا ينشئ التسجيل هذا الاعتماد ولا يختلق أرقامًا أو سجلات.

## التشغيل والتنظيف
```powershell
php artisan migrate --force
php artisan serve --host=127.0.0.1 --port=8000
php artisan schedule:run
```
يشغّل المجدول `registration:prune` كل ساعة لحذف المحاولات المؤقتة المنتهية فقط. يمكن تشغيل `php artisan registration:prune` يدويًا. يجب تشغيل مجدول Laravel دوريًا في الاستضافة. لا تنفذ migrate:fresh. Migration إضافية تحتفظ بالبيانات، وتستكمل التنفيذ الجزئي في MySQL. rollback مرفوض صراحة للحفاظ على حقول الهوية وسجل Migration؛ استخدم Migration أمامية مراجعة عند الحاجة إلى تغييرها.

## الملفات الخاصة بهذه المهمة
جديدة:
- `app/Contracts/RegistrationSms.php`
- `app/Http/Controllers/RegistrationController.php`
- `app/Http/Requests/RegisterCitizenRequest.php`
- `app/Services/Registration/CitizenRegistration.php`
- `app/Services/Registration/UnavailableSms.php`
- `config/registration.php`
- `database/migrations/2026_11_02_000001_add_staged_registration.php`
- `public/css/registration.css`، `public/js/registration.js`
- `resources/views/auth/terms.blade.php`
- `tests/Feature/RegistrationFlowTest.php`، `tests/frontend/registration.test.cjs`
- هذا الملف و`docs/registration-implementation-progress.md`.

معدلة موضعيًا (مع الاحتفاظ بالتعديلات السابقة):
- `routes/web.php`، `routes/console.php`، `bootstrap/app.php`
- `app/Providers/AppServiceProvider.php`، `app/Models/User.php`
- `app/Http/Controllers/AuthController.php`، `ProfileController.php`، `CitizenDataController.php`، `AdminGovernmentServiceController.php`
- `app/Http/Requests/SaveServiceAssessmentRequest.php`
- `app/Services/ServiceEligibility.php`، `ServiceRequestSubmission.php`، `Review/ReviewSnapshot.php`، `Integrations/VerificationContext.php`، `Attachments/ManageAttachments.php`
- `resources/views/auth/register.blade.php`، `auth/login.blade.php`، `layouts/public.blade.php`، `profile/edit.blade.php`، `components/citizen-details.blade.php`، `employee/citizens/index.blade.php`، `citizen/applications/create.blade.php`، `citizen/applications/preview.blade.php`، `admin/services/edit.blade.php`
- `tests/Feature/UserAuthenticationTest.php` لتكييف التسجيل عبر OTP مع الاحتفاظ باختبارات الدخول والصلاحيات السابقة.

## التحقق والاستئناف
الأوامر: `php vendor/bin/phpunit --do-not-cache-result`، واختبارات MySQL على قاعدة منفصلة ينتهي اسمها بـ`_testing` فقط، و`node --test tests/frontend/registration.test.cjs tests/frontend/portal-demo.test.cjs`، وفحص Pint وBlade والمسارات. تستعمل الاختبارات migrations إضافية ومعاملات للتراجع عن بياناتها، ولا تعيد إنشاء قاعدة المستخدم.

النتائج النهائية الفعلية وحدود الفحص موثقة في [ملف التقدم](registration-implementation-progress.md). نجحت 10 اختبارات JavaScript (5 للتسجيل و5 موجودة). لا يوجد متصفح متصل في هذه الجلسة؛ لم يجر فحص بصري أو تجربة SMS حقيقية. ملف التقدم يحفظ نقطة الاستئناف، ولا يمنع انقطاع مصادقة أدوات Codex.

## Local OTP demo
Set REGISTRATION_LOCAL_OTP=true only with APP_ENV=local, then run php artisan config:clear. The existing verification page displays a random code only in the originating session (private/no-store). Existing expiry, resend and attempt limits remain. No SMS is sent and phone_verified_at remains null for these accounts. Production ignores the flag and rejects local pending verification. Turn the flag off and configure RegistrationSms for real use. Never deploy demo accounts as verified phone owners.
Validation: 56 registration/auth tests passed (412 assertions); Blade compiled successfully.
