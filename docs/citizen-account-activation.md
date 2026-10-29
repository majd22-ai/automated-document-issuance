# الدخول وتفعيل حساب المواطن

## تحديث الاعتماد بالحضور — 2026-10-29

أصبح اعتماد التفعيل يتطلب خطوات إضافية على الحساب الموجود: مراجعة الهوية والهاتف، نجاح محاكاة السبابة اليمنى ثم اليسرى، إصدار رمز من ستة أرقام، ثم إدخاله والضغط على «موافق». تظل الإقرارات والمراجع الرسمية وشروط إصدار الحساب الأولي أدناه مطلوبة، ولا يكفي نجاح المحاكاة وحده.

إعدادات التجربة المحلية:

```dotenv
APP_ENV=local
REGISTRATION_LOCAL_OTP=true
ACTIVATION_LOCAL_FINGERPRINTS=true
```

تم تمكين علم محاكاة الأصابع في الإعداد المحلي الحالي فقط؛ القيم النموذجية في `.env.example` معطلة. لا تغير `APP_ENV` في الإنتاج لتجاوز الشروط.

شغّل MySQL ثم `php artisan migrate` و`php artisan serve`. افتح `/employee/accounts` بحساب موظف نشط لديه `can_activate` لخدمة إصدار البطاقة لأول مرة. ابحث عن المواطن أو افتح طلب تفعيله، ثم احفظ الهوية والهاتف. يعرض الرقم الوطني من `national_id` صراحة، ولا يستنتجه من اسم المستخدم أو الهاتف أو رقم الحساب. في مسار عدم وجود هوية يعرض رقم شهادة الميلاد منفصلًا بنوعه ومصدره. للحساب الأولي لا تتحول شهادة الميلاد إلى رقم وطني؛ لا يزال يلزم طلب إصدار وتسليم مكتمل وإقرار الإصدار الرسمي.

تعديل الهاتف يحتاج إقرارًا ظاهرًا، ويطبق على الحساب فقط داخل معاملة الاعتماد الناجحة؛ يسجل التدقيق أسماء الحقول المتغيرة دون نشر الرقم. ينتج كل إصبع سجل محاولة مستقلًا بمصدر «محاكاة بصمة محلية» والموظف والوقت والنتيجة، مع خيار فشل وإعادة محاولة. النافذة قابلة للإغلاق بـ× أو Escape وتستخدم حوار HTML الأصلي لدعم لوحة المفاتيح وحصر التركيز.

عند نجاح الإصبعين، اطلب رمز الهاتف. مع `LocalSms` يظهر للموظف المخول فقط في نافذة خاصة، مع تنبيه «رمز تجريبي محلي لم يُرسل إلى شبكة الهاتف». يمكن نسخه ويُمحى من النافذة عند إغلاقها. لا يحفظ في جلسة Laravel أو سجل التدقيق؛ قاعدة البيانات تحتوي تجزئته فقط. أدخله في حقل التحقق وأكمل الإقرارات ثم «موافق». تحفظ المصادر في سجل الاعتماد نفسه. لا ينشأ حساب ولا تتغير كلمة مروره.

جلسة الحضور مرتبطة بالمواطن والموظف وبيانات الحساب والهاتف لمدة ساعة. تغيير بيانات الحساب أو إعادة حفظ الحضور يبطل الأدلة السابقة دون حذف محاولاتها. مدة OTP الافتراضية عشر دقائق، خمس محاولات، ثلاث رسائل، وستون ثانية قبل إعادة الإرسال؛ توجد حدود إضافية لكل هاتف وبدء الجلسات والطلبات. الرمز المستهلك لا ينفذ اعتمادًا جديدًا؛ إعادة النموذج المطابق تعيد الاعتماد السابق دون تكراره. لا تُعاد محاولة الإرسال للمزود تلقائيًا.

Migration الجديدة `2026_11_05_000001_add_in_person_activation_checks.php` توسع `citizen_activation_requests` بحالة جلسة الحضور والهوية والهاتف المشفرين وحالة OTP، وتضيف مصادر التحقق إلى `citizen_account_confirmations`. الجدول الوحيد الجديد `activation_fingerprint_attempts` يحتفظ بمحاولات الأصابع؛ جدول بصمة طلب البطاقة مرتبط بنموذج إصدار بطاقة مختلف فلا يستخدم لتفعيل الحساب. لا يحتوي أي منهما صورة إصبع أو قالبًا حيويًا. طبقت Migration محليًا دون حذف سجل تاريخي.

المسارات الإضافية المحمية تحت `/employee/accounts/{citizen}`: `prepare` لحفظ جلسة الحضور، و`finger` للمحاكاة، و`code` للإرسال. أصبح `confirm` يتطلب أيضًا `visit_token` و`otp`. الحقول الحساسة، ومنها OTP، مستثناة من old input عند الأخطاء.

خارج البيئة المحلية تُرفض محاكاة الأصابع وأدلتها. لا يوجد SMS حقيقي أو جهاز حقيقي مربوط حاليًا؛ غياب التكامل يمنع الاعتماد برسالة واضحة. ربط الرسائل يحتاج محولًا موثقًا ينفذ `RegistrationSms` ويرفض فشل التسليم، وربط البصمة يحتاج موصلًا موثقًا وأدلة مصدر حقيقي ومسار اعتماد مستقلًا. مجرد تغيير المزود أو أعلام الإعداد لا يحول النتائج التجريبية إلى أدلة حيوية حقيقية.

أهم ملفات التحديث: `InPersonActivation.php`، `ActivationFingerprintAttempt.php`، Migration أعلاه، خدمات ومتحكم وطلب التفعيل القائمون، `employee/accounts/in-person.blade.php` و`show.blade.php`، `activation-visit.js`، إعدادات التسجيل والمسارات ومنع حفظ OTP في الأخطاء، واختبارات `InPersonActivationTest` و`CitizenActivationTest` والتزامن و`activation-visit.test.cjs`.

### نتائج التحقق لهذا التحديث

- مجموعة Laravel الكاملة: 355 اختبارًا و3068 تأكيدًا، نجحت؛ السجل `storage/logs/activation-in-person-tests.log`.
- بعد إضافة ثلاث حالات إضافية: الاختبارات المركزة الجديدة والقديمة معًا 45 اختبارًا و374 تأكيدًا، نجحت.
- السيناريوهات التسعة الأولى للتدفق الجديد على MySQL المعزولة: 9 اختبارات و122 تأكيدًا، نجحت.
- تزامن MySQL على قاعدة اختبار معزولة: 3 اختبارات و21 تأكيدًا، نجحت. تشمل اعتمادين متنافسين وإعادة نفس العملية وتعارض ربط هوية حسابين.
- JavaScript: 29 اختبارًا ناجحًا؛ تشمل انتظار الخادم ومنع ضغط الزر المكرر، محو الرمز عند إغلاق النافذة، وإعادة استخدام مفتاح المحاولة عند عدم وضوح نتيجة الاتصال.
- نجح Pint للملفات المعدلة، وفحص PHP للمسارات، وتجميع واجهات Blade، وتطبيق Migration على MySQL المحلية.
- لم تُنفذ مراجعة مرئية تفاعلية في متصفح أو تجربة SMS أو بصمة حقيقية. فحص واجهة JavaScript آلي باستخدام Node.

أوامر إعادة الفحص المعزول:

```powershell
php vendor/phpunit/phpunit/phpunit --filter='InPersonActivationTest|CitizenActivationTest' --do-not-cache-result
node --test tests/frontend/*.test.cjs
php artisan view:cache
php artisan view:clear
```

الملفات المتغيرة لهذا التحديث، إضافة إلى الدليل الحالي:

- جديدة: `app/Services/Registration/InPersonActivation.php`، `app/Models/ActivationFingerprintAttempt.php`، `database/migrations/2026_11_05_000001_add_in_person_activation_checks.php`، `resources/views/employee/accounts/in-person.blade.php`، `public/js/activation-visit.js`، `tests/Feature/InPersonActivationTest.php`، `tests/Support/PreparesActivationProof.php`، `tests/frontend/activation-visit.test.cjs`، `docs/activation-in-person-plan.md`.
- قائمة عُدلت: `app/Services/Registration/CitizenAccountActivation.php` و`LocalSms.php`، `app/Models/CitizenActivationRequest.php` و`CitizenAccountConfirmation.php`، `app/Http/Controllers/CitizenAccountController.php`، `app/Http/Requests/ConfirmCitizenAccountRequest.php`، `resources/views/employee/accounts/show.blade.php`، `routes/web.php`، `config/registration.php`، `bootstrap/app.php`، `.env.example` وعلم المحاكاة المحلي في `.env`، `tests/Feature/CitizenActivationTest.php`، `tests/Integration/CitizenActivationConcurrencyTest.php`، `tests/Support/activation-worker.php`.

## السلوك المنفذ
يدخل المواطن بالرقم الوطني أو اسم المستخدم في الحقل نفسه، وكلاهما يحيل إلى User.id نفسه وكلمة المرور نفسها. بقي الدخول بالبريد الإلكتروني للحسابات القائمة متوافقًا. لا ينشئ الدخول حسابًا أو كلمة مرور. إذا تطابق المعرّف مع حسابين مختلفين، ترفض المصادقة برسالة عامة دون تجربة كلمة المرور لاختيار أحدهما.

الحالات منفصلة:

| الحالة | الدخول | الخدمات المقيدة |
|---|---|---|
| initial_account | باسم المستخدم؛ الهاتف ليس رقم هوية | إصدار البطاقة لأول مرة ومرفقاته ومتابعة الطلب فقط، مع شروط الخدمة القائمة |
| identity_verification_pending | بالهوية/اسم المستخدم وكلمة المرور | محجوبة؛ تظهر تعليمات الحضور |
| active مع account_activated_at دون identity_verified_at | مسموح، مع عرض أن الهوية لم تكتمل | محجوبة حتى استكمال توثيق الهوية |
| active مع تاريخي الاعتماد وهوية مرتبطة | بالمعرّفين على الحساب نفسه | يسمح بفحص الأهلية، وتبقى شروط كل خدمة ومراجعاتها مطلوبة |
| review_required | مسموح بعرض الحالة والمتابعة | محجوبة إلى حين المراجعة الآمنة |
| is_active=false | مرفوض | محجوبة؛ لا يعيد إجراء التفعيل تشغيل الحساب المعطّل |

الحسابات السابقة ذات registration_status=NULL تحافظ على سياسة المصادقة والتقييم الخاص بكل خدمة؛ لم تُحوّل تلقائيًا إلى هوية موثقة عامة. role لا يتغير عند اعتماد المواطن. التسجيل والتحقق من الجوال لا يفعّلان الحساب ولا يثبتان الهوية المدنية أو البريد.

## طريقة الاستخدام
1. يدخل المسؤول إلى `/admin/review-permissions`، ويختار موظفًا نشطًا وخدمة **إصدار البطاقة الشخصية لأول مرة**، ثم يمنح «اعتماد تفعيل المواطن». الصلاحية الجديدة `can_activate` مستقلة ولا تمنح تلقائيًا مع المراجعة أو الإصدار أو دور admin. لا يقبل الخادم منحها ضمن خدمة أخرى.
2. يدخل الموظف المخوّل إلى `/employee/accounts` للبحث بالاسم الكامل أو الرقم الوطني أو اسم المستخدم، أو تصفية الحالات، مع Pagination. تظهر له بيانات الحساب ومتطلبات الاعتماد وسجل إجراءاته.
3. صاحب الرقم الوطني المسجل: يسجل الموظف مرجع الإجراء الرسمي ويؤكد التفعيل. إذا لم يكتمل التحقق الرسمي من الهوية، يسجل التفعيل وحده وتبقى الهوية غير موثقة والخدمات المقيدة محجوبة. يمكن استكمال التحقق لاحقًا بإجراء جديد موثق.
4. صاحب الحساب الأولي: يكمل طلب البطاقة نفسه وفق نظام المراجعة والإصدار والتسليم الموجود. بعد أن يصبح الطلب completed بسجل وثيقة مرتبط صحيح وساري، يؤكد الموظف **بشكل منفصل** اكتمال الإصدار الرسمي وصدور الرقم الوطني والبطاقة، ويدخل الرقم الوطني الرسمي ومرجع الإثبات وإقرار التحقق من الهوية. لا يُستخرج الرقم الوطني من رقم الوثيقة الداخلي أو الهاتف أو رقم الطلب.
5. تحفظ المعاملة الربط والتفعيل وتوثيق الهوية والتأكيد والتدقيق والإشعار الداخلي على الحساب الحالي. يبقى الطلب مكتملًا وفق مسار إصداره وتسليمه، ويضاف حدث تأكيد الإصدار الرسمي إلى خطه الزمني. لا تتجاوز هذه العملية بوابات الإصدار والتسليم أو تنشئ طلبًا بديلًا.
6. تعرض صفحة `/citizen/account/status` الحالة وتعليمات الحضور والمتابعة. يوجّه الدخول إليها للحساب الجديد المنتظر أو الذي يحتاج مراجعة. عند تغيّر الاعتماد تُلغى الجلسات القديمة عبر auth_version وremember_token، ثم يعيد المواطن الدخول بكلمة المرور نفسها؛ لا ينشأ حساب جديد.

## الحماية والتعارض
- Form Request وUserPolicy وصلاحية الخدمة تُفحص في الخادم. تُقفل حسابات المنفذ والمواطن بترتيب المعرّف، ثم الخدمة وصلاحيتها والطلب؛ يعاد فحص الدور والنشاط والصلاحية داخل المعاملة بعد القفل. إلغاء المنحة يمنع العمليات اللاحقة، حتى لو كانت الصفحة مفتوحة مسبقًا.
- تتشارك كتابة معرّفات التسجيل وإدارة الموظفين والتفعيل قفل Cache خادميًا. يعاد فحص تعارض الرقم الوطني واسم المستخدم داخل المعاملة، مع قيود uniqueness الموجودة في users. لا تستخدم Cache ذاكرة محليًا لكل عامل في الإنتاج؛ يلزم مخزن مشترك يدعم الأقفال (قاعدة البيانات مستخدمة محليًا).
- operation_token UUID فريد مع بصمة HMAC للمحتوى وارتباط صريح بالمنفذ والمواطن؛ التكرار المطابق يعيد السجل نفسه، وتغيير المحتوى أو المنفذ أو الحساب برمز مستخدم مرفوض. طلب اعتماد جديد لحالة مكتملة مرفوض.
- إذا كان الرقم مرتبطًا بحساب آخر أو يطابق اسم مستخدمه، لا يربط الرقم ولا تدمج الحسابات ولا تنتقل الطلبات؛ يسجل التعارض وتصبح الحالة review_required، وتظهر في قائمة المراجعة ويصل إشعار عام للمواطن. حل التعارض يحتاج تحقيقًا موثقًا؛ لم تُضف آلية دمج حسابات تلقائية.
- مرجع الإثبات مشفر في citizen_account_confirmations. السجل محمي من التعديل والحذف عبر النموذج، ولا يعرض المرجع أو بصمة العملية أو رمزها في الاستجابات أو تاريخ الواجهة.
- التدقيق يحتفظ بالمنفذ والوقت والحساب والإجراء والنتيجة وأسماء الحقول المتغيرة/القيم غير الحساسة فقط، دون أرقام وطنية أو هاتف أو مرجع إثبات أو صور أو بصمات. يُمنع تمرير تاريخي التفعيل والتوثيق من التسجيل والملف الشخصي. عند فشل حفظ التدقيق تتراجع المعاملة والإشعار والاعتماد معًا.

## قاعدة البيانات والمسارات
Migration إضافية: `2026_11_03_000001_add_citizen_account_activation.php`:
- users.account_activated_at وusers.identity_verified_at، nullable datetime؛ لا تغيير للسجلات الموجودة ولا اعتماد تلقائي.
- service_review_permissions.can_activate، boolean، default false.
- citizen_account_confirmations: المالك والمنفذ والطلب الاختياري ورمز العملية الفريد والإجراء والنتيجة ومرجع مشفر وبصمة HMAC وإقرار توثيق الهوية ووقت الإجراء، مع قيود العلاقات وفهرس تاريخ الحساب.
- Migration تحتفظ بتاريخ الاعتماد، وتمنع rollback صراحة؛ التراجع البنيوي يحتاج Migration أمامية مراجعة.

المسارات الجديدة ضمن جلسات Laravel وCSRF الحالية:
- GET `/citizen/account/status` للمواطن صاحب الجلسة.
- GET `/employee/accounts` وGET `/employee/accounts/{citizen}` للموظف المخوّل.
- POST `/employee/accounts/{citizen}/confirm`، بحد 10 طلبات/دقيقة، للتحقق والاعتماد. الحقول: action (`activate` أو `confirm_issuance`)، national_id، request_id عند تأكيد الإصدار، identity_confirmed، official_issuance_confirmed، evidence_reference، operation_token.

لم يتغير عقد حقلي identifier/password في POST `/login`. استُخدمت المصادقة والجلسات والمراجعة والإشعارات والتدقيق القائمة؛ لا مكتبات جديدة ولا نظام أدوار موازٍ.

## الملفات الخاصة بهذه المهمة
جديدة:
- `database/migrations/2026_11_03_000001_add_citizen_account_activation.php`
- `app/Models/CitizenAccountConfirmation.php`
- `app/Services/Registration/CitizenIdentifiers.php` و`CitizenAccountActivation.php`
- `app/Http/Requests/ConfirmCitizenAccountRequest.php`
- `app/Http/Controllers/CitizenAccountController.php`
- `resources/views/citizen/account-status.blade.php`
- `resources/views/employee/accounts/index.blade.php` و`show.blade.php`
- `tests/Feature/CitizenActivationTest.php`
- `tests/Integration/CitizenActivationConcurrencyTest.php`
- `tests/Support/activation-worker.php`
- هذا الدليل.

معدلة موضعيًا، مع الاحتفاظ بالتعديلات السابقة:
- `app/Http/Controllers/AuthController.php`، `ProfileController.php`، `EmployeeManagementController.php`، `ReviewPermissionController.php`، `CitizenNotificationController.php`.
- `app/Http/Requests/RegisterCitizenRequest.php`، `app/Models/User.php`، `ServiceReviewPermission.php`.
- `app/Policies/UserPolicy.php`، `CitizenServiceRequestPolicy.php`.
- `app/Services/Registration/CitizenRegistration.php`، `Services/ServiceEligibility.php`، `Services/Attachments/ManageAttachments.php`، `Services/ServiceRequestSubmission.php`، `Services/Review/ReviewSnapshot.php`، `Services/Tracking/ApplicationTimeline.php`، `Services/Notifications/CitizenUpdates.php`، `Services/Oversight/AuditTrail.php`.
- `app/Observers/OperationalAuditObserver.php`، `bootstrap/app.php`، `routes/web.php`.
- `resources/views/auth/login.blade.php`، `components/public-header.blade.php`، `components/citizen-details.blade.php`، `citizen/dashboard.blade.php`، `admin/review-permissions.blade.php`.
- `docs/registration-implementation-progress.md`، مع الإبقاء على نتائج المهمة السابقة وتمييز نتائج هذه المهمة.

## الاختبارات والتشغيل
```powershell
php artisan migrate --force
php vendor/bin/phpunit --do-not-cache-result
node --test tests/frontend/registration.test.cjs tests/frontend/portal-demo.test.cjs
```
اختبارات MySQL وIntegration تُشغل بمتغيرات DB_CONNECTION=mysql وDB_DATABASE=e_issuance_auth_testing وإعدادات الاتصال المحلية خارج Git، ثم:
```powershell
php vendor/bin/phpunit --do-not-cache-result
php vendor/bin/phpunit --do-not-cache-result tests/Integration
```
تحرس الاختبارات قاعدة البيانات، وتستخدم معاملات أو fixtures محصورة بقاعدة `_testing`. لا تنفذ migrate:fresh على المشروع. النتائج النهائية الفعلية في [ملف التقدم](registration-implementation-progress.md).

## التكاملات والحدود
لا يوجد تكامل حكومي فعلي يؤكد إصدار البطاقة. المسار العامل هو **تأكيد إداري يدوي آمن من موظف مخوّل بمرجع رسمي** بعد إنهاء إجراءات الطلب القائمة؛ لا يتم التفعيل بسبب البصمة أو الصورة أو الإصدار الداخلي وحدها، ولا يوجد webhook وهمي.

إشعارات الاعتماد داخلية فعلية في قاعدة البيانات؛ لم تُرسل SMS أو رسائل حكومية أو بريد خارجي في الاختبارات. SMS التسجيل ما زال غير مربوط كما في [دليل التسجيل](registration.md)، ولذلك يحتاج إكمال التسجيل الحقيقي إلى مزود مخوّل وإعداداته. لم تضف المهمة أسرارًا أو متغيرات بيئة لمزود غير معروف. التكامل الرسمي المستقبلي يحتاج عقدًا ومصدرًا موثوقًا وسياسة اعتماد الموظف، ويجب أن يعيد استخدام بوابة الاعتماد هذه دون تجاوز الصلاحيات.

لم يتوفر متصفح متصل لهذه الجلسة؛ فُحصت القوالب والاستجابات والاختبارات البرمجية، ولم ينفذ فحص بصري حقيقي للجوال/سطح المكتب. الواجهات تستخدم تصميم المشروع والعربية وRTL والجداول القابلة للتمرير.
