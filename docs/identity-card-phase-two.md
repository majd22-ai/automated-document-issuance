# مصادقة استمارة البطاقة ومحاكاة إشعارات الجوال — الجزء الثاني

## ما أصبح يعمل

تستكمل الإضافة بنية الجزء الأول: `identity_card_forms` والطلب المرتبط `citizen_service_requests` والشاهدان في `identity_card_witnesses`. أضيف جدول واحد `identity_card_attestations`، وفيه سجل المصادقة وإشعاره التجريبي الواحد ووقت قراءته. لا حسابات جهات خارجية ولا أدوار جديدة ولا إرسال SMS حقيقي.

بعد إرسال الاستمارة، يختار المواطن أحد المسارات:

- جهة العمل: سجل مصادقة وإشعار محفوظ، ومرحلة الاستمارة بانتظار مصادقة جهة العمل.
- عاقل الحارة: سجل وإشعار مماثل. الموافقة التجريبية تترك إجراء إدارة الأمن الورقي في حالة انتظار مستقلة، دون ادعاء تنفيذه أو توفير ختم رسمي.
- الشاهدان: لا إشعار جوال. يسجل الموظف حضور كل شاهد ونتيجة فحص وثيقته في السجلات الموجودة. الموافقة تتطلب حضور الاثنين وقبول فحص وثائقهما؛ لا يُفترض الحضور من البيانات المدخلة.

أيقونة «محاكاة إشعارات الجوال» عائمة خارج الهيدر، وتظهر للمخولين فقط. تجلب الإشعارات كل 20 ثانية أثناء ظهور الصفحة، وتفتح النافذة عند إشعار غير مقروء جديد. الإغلاق يخفي النافذة فقط. «عرض الطلب» يرسل POST محميًا بـCSRF لتسجيل القراءة ثم يفتح السجل الصحيح. الإشعار يعرض اسم المواطن ومرجعه الداخلي والجهة والمستلم والنوع والوقت، دون رقم هويته أو وثائق الشهود أو أرقام الهاتف أو العنوان.

الموافقة والرفض محفوظان مرة واحدة ضمن معاملة وقفل على سجلات المواطن والخدمة والطلب والاستمارة والمصادقة. الرفض يتطلب سببًا، وتظهر النتيجة والسبب للحسابات المخولة، ويصل إشعار نتيجة داخل المنصة للمواطن عبر صندوق الإشعارات الموجود. لا ختم في حالة الانتظار أو الرفض. بعد الموافقة يظهر ختم نصي تجريبي واضح مع نوع الجهة والمعتمد والوقت ومرجع `SIM-ATT-…`.

تُعرض مرحلة الاستمارة مع حالة الطلب الأساسية الموجودة، دون اختراع حالات بديلة للـworkflow العام. تبقى المصادقة المرفوضة أو الناقصة مانعًا من القبول والاعتماد والإصدار اللذين يتطلبانها عبر فحوص الأهلية الحالية. الرفض الإداري أو إلغاء الطلب الأساسي يلغي المصادقة المعلقة. الموافقة التجريبية ليست اعتمادًا حكوميًا ولا يُعتد بها خارج وضع المحاكاة المحلي.

## الصلاحيات والتجربة

- يلزم `APP_ENV=local` و`IDENTITY_ATTESTATION_SIMULATION=true`؛ الشرط المحلي إلزامي حتى لو فُعّل العلم في الإنتاج.
- الموظف يحتاج صلاحية `can_review` على خدمة إصدار البطاقة نفسها. المدير النشط مخوّل بوضع المحاكاة.
- المواطن يختار مسار استمارته المرسلة فقط؛ لا يستطيع فتح شاشة المستلم أو قراءة قائمتها أو اعتماد الطلب.
- يبقى اعتماد الشخص طلبه الخاص ممنوعًا حتى لو تغير دوره لاحقًا إلى موظف أو مدير.
- لا يُستخدم اسم الجهة أو رقم هاتف لإثبات هوية جهة حكومية؛ الشاشة تمثيل محلي لدور المستلم.

روابط التجربة:

1. المواطن: `/citizen/identity-card`، ثم افتح الاستمارة المرسلة واختر المسار.
2. الموظف المخول أو المدير: `/simulation/identity-attestations`، أو افتح الأيقونة العائمة.
3. وافق أو ارفض مع السبب، أو سجل حضور الشاهدين والتحقق منهما. راجع مرحلة الاستمارة والختم التجريبي بعد الموافقة.

## الملفات

جديدة:

- `database/migrations/2026_10_29_000002_create_identity_card_attestations.php`
- `app/Models/IdentityCardAttestation.php`
- `app/Policies/IdentityCardFormPolicy.php`
- `app/Services/IdentityAttestations.php`
- `app/Http/Controllers/IdentityAttestationController.php`
- `app/Http/Requests/ChooseIdentityAttestation.php` و`DecideIdentityAttestation.php`
- `app/Observers/IdentityAttestationObserver.php`
- `resources/views/components/identity-card-attestation.blade.php` و`identity-attestation-stamp.blade.php` و`mobile-attestation-simulator.blade.php`
- `resources/views/simulation/identity-attestations/index.blade.php` و`show.blade.php`
- `public/css/identity-attestation.css`
- `public/js/identity-attestation.js` و`mobile-attestation-simulator.js`
- `tests/Feature/IdentityAttestationTest.php`
- `tests/Integration/IdentityAttestationConcurrencyTest.php`
- `tests/Support/identity-attestation-worker.php`
- `tests/frontend/identity-attestation.test.cjs`

معدلة للربط:

- `.env.example` و`config/identity_card.php` و`routes/web.php`
- `app/Models/IdentityCardForm.php` و`IdentityCardWitness.php`
- `app/Providers/AppServiceProvider.php` و`app/Services/ServiceEligibility.php`
- `resources/views/layouts/public.blade.php`
- `resources/views/citizen/identity-card/wizard.blade.php` و`resources/views/employee/identity-card/show.blade.php`
- `resources/views/components/identity-card-summary.blade.php` و`request-details.blade.php`

## التشغيل والتحقق

طُبقت الـMigration محليًا دون حذف بيانات. على بيئة أخرى:

```powershell
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

نتائج التحقق الفعلية:

- المجموعة الكاملة: **338 اختبار Laravel و2663 تحققًا** نجحت.
- فحوص الجزء الثاني والمراجعة والجزء الأول: **32 اختبارًا و423 تحققًا** نجحت.
- فحوص الجزء الثاني على MySQL المعزول: **7 اختبارات و150 تحققًا** نجحت.
- اختبار قراري موافقة ورفض متزامنين على MySQL المعزول: **اختبار واحد و7 تحققات** نجحت؛ سُجل قرار واحد وإشعار نتيجة واحد فقط.
- اختبارات الواجهة البرمجية لمحاكاة الجوال والرفض ومعاينة الصورة: **6 اختبارات JavaScript** نجحت، ومنها الإغلاق وإعادة الفتح وعدم تغيير حالة القراءة عند الإغلاق ومنع إرسال CSRF إلى رابط خارجي.
- تجميع Blade وفحص Pint نجحا. أُجريت مراجعة كود مستقلة وأضيف منع اعتماد المالك لطلبه بعد تغيير دوره.
- لم تتوفر جلسة متصفح للمعاينة البصرية؛ فحوص الواجهة هنا اختبارات HTML وJavaScript.

```powershell
php vendor/bin/phpunit tests/Feature/IdentityAttestationTest.php tests/Feature/IdentityCardWizardTest.php --do-not-cache-result
node --test tests/frontend/identity-attestation.test.cjs tests/frontend/identity-card-wizard.test.cjs
```

اختبارات MySQL والتزامن لا تعمل إلا على قاعدة معزولة ينتهي اسمها بـ`_testing`. لم يبدأ الجزء الثالث أو أي محاكاة لجهاز البصمة.
