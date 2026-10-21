/* Frontend prototype only. No network requests, real payments or official documents. */
(() => {
  'use strict';
  const icon = name => '<svg class="site-icon" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="/assets/icons/outline.svg#' + name + '"/></svg>';
  const navIcons = {dashboard:'home', new:'plus', requests:'file', appointments:'calendar', payments:'card', documents:'identity', notifications:'bell', review:'shield', issuance:'check'};
  const KEY = 'eissuance-demo-v1';
  const services = [
    ['identity-first-issue', 'إصدار بطاقة هوية لأول مرة', 1500],
    ['identity-renewal', 'تجديد بطاقة الهوية', 1500],
    ['identity-lost', 'بدل فاقد بطاقة الهوية', 2000],
    ['passports-first-issue', 'إصدار جواز سفر', 12000],
    ['passports-renewal', 'تجديد جواز السفر', 10000],
    ['driving-first-issue', 'إصدار رخصة قيادة', 5000],
    ['driving-renewal', 'تجديد رخصة القيادة', 3500],
  ];
  const labels = { submitted: 'قيد المراجعة', 'needs-info': 'يحتاج استكمال', approved: 'معتمد', rejected: 'مرفوض', issued: 'جاهز للتسليم', completed: 'مكتمل' };
  const now = () => new Date().toISOString();
  const today = () => { const d = new Date(); return [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-'); };
  const uid = () => 'DEMO-' + Date.now().toString(36).toUpperCase() + '-' + Math.random().toString(36).slice(2, 6).toUpperCase();
  const findService = id => services.find(s => s[0] === id);
  const seed = () => ({
    version: 1,
    requests: [
      { id: 'DEMO-1001', service: 'identity-renewal', name: 'أحمد محمد علي', nationalId: '1000000001', phone: '777000001', created: now(), status: 'submitted', paid: false, files: [{ name: 'صورة-شخصية.jpg', size: 180000, type: 'image/jpeg' }], history: [{ status: 'submitted', note: 'طلب تجريبي لتجربة المراجعة', at: now() }] },
      { id: 'DEMO-1002', service: 'passports-renewal', name: 'أحمد محمد علي', nationalId: '1000000001', phone: '777000001', created: now(), status: 'needs-info', paid: false, files: [{ name: 'جواز-سابق.pdf', size: 230000, type: 'application/pdf' }], history: [{ status: 'submitted', note: 'تم تقديم الطلب', at: now() }, { status: 'needs-info', note: 'يرجى إرفاق صورة شخصية حديثة وواضحة', at: now() }] },
      { id: 'DEMO-1003', service: 'driving-renewal', name: 'أحمد محمد علي', nationalId: '1000000001', phone: '777000001', created: now(), status: 'issued', paid: true, receipt: 'DEMO-RECEIPT-1003', method: 'محفظة تجريبية', documentNumber: 'SAMPLE-1003', files: [], history: [{ status: 'approved', note: 'اعتماد الطلب التجريبي', at: now() }, { status: 'issued', note: 'الوثيقة جاهزة للاستلام', at: now() }] },
    ],
    notifications: [{ id: 'welcome', title: 'مرحبًا بك في المعاينة التفاعلية', text: 'جميع البيانات والرسوم والوثائق المعروضة تجريبية.', at: now(), read: false }],
  });
  let state;
  let storageAvailable = true;
  try {
    const saved = JSON.parse(localStorage.getItem(KEY) || 'null');
    state = saved && saved.version === 1 && Array.isArray(saved.requests) && Array.isArray(saved.notifications) &&
      saved.requests.every(r => r && findService(r.service) && labels[r.status] && Array.isArray(r.files) && Array.isArray(r.history)) ? saved : seed();
  } catch { state = seed(); storageAvailable = false; }
  const save = () => { try { localStorage.setItem(KEY, JSON.stringify(state)); storageAvailable = true; } catch { storageAvailable = false; } };
  function notify(r, title, text = '') {
    state.notifications.unshift({ id: uid(), requestId: r.id, title, text, at: now(), read: false });
  }
  function request(id) { const r = state.requests.find(x => x.id === id); if (!r) throw Error('الطلب غير موجود.'); return r; }
  function validateFiles(files) {
    if (!files || !files.length) throw Error('أرفق صورة أو مستندًا واحدًا على الأقل.');
    if (files.length > 5) throw Error('الحد الأقصى خمسة مرفقات.');
    for (const f of files) {
      if (!['image/jpeg', 'image/png', 'application/pdf'].includes(f.type) || !/\.(jpe?g|png|pdf)$/i.test(f.name)) throw Error('المرفقات المقبولة: JPG وPNG وPDF فقط.');
      if (!f.size || f.size > 5 * 1024 * 1024) throw Error('حجم كل ملف يجب أن يكون أكبر من صفر وأقل من أو يساوي 5 ميجابايت.');
    }
  }
  function create(data) {
    if (!findService(data.service) || !data.name?.trim() || !/^\d{6,20}$/.test(data.nationalId || '') || !/^\d{7,15}$/.test(data.phone || '')) throw Error('تحقق من الاسم والرقم الوطني (6–20 رقمًا) والهاتف (7–15 رقمًا).');
    validateFiles(data.files);
    if (state.requests.some(r => r.service === data.service && r.nationalId === data.nationalId && !['completed', 'rejected'].includes(r.status))) throw Error('يوجد طلب قائم للخدمة نفسها؛ أكمله أولًا.');
    const r = { id: uid(), service: data.service, name: data.name.trim(), nationalId: data.nationalId, phone: data.phone, created: now(), status: 'submitted', paid: false,
      files: data.files.map(f => ({ name: f.name, size: f.size, type: f.type })), history: [{ status: 'submitted', note: 'تم تقديم الطلب بنجاح', at: now() }] };
    state.requests.unshift(r); notify(r, 'تم استلام طلبك', r.id); save(); return r;
  }
  function resubmit(id, files) {
    const r = request(id); if (r.status !== 'needs-info') throw Error('هذا الطلب لا يحتاج إلى استكمال.');
    validateFiles(files); r.files = files.map(f => ({ name: f.name, size: f.size, type: f.type }));
    r.status = 'submitted'; r.history.push({ status: r.status, note: 'تم تحديث المرفقات وإعادة إرسال الطلب', at: now() }); notify(r, 'تم إرسال الاستكمال'); save();
  }
  function book(id, date, time, center) {
    const r = request(id);
    if (['completed', 'rejected'].includes(r.status)) throw Error('لا يمكن حجز موعد لطلب مغلق.');
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !Number.isFinite(Date.parse(date)) || date < today() || !['09:00', '10:00', '11:00', '12:00'].includes(time) || !['صنعاء', 'عدن', 'تعز'].includes(center)) throw Error('اختر تاريخًا صالحًا من اليوم أو بعده ومركزًا ووقتًا متاحين.');
    if (state.requests.some(x => x.id !== id && x.appointment?.date === date && x.appointment?.time === time && x.appointment?.center === center)) throw Error('هذا الموعد محجوز، اختر وقتًا آخر.');
    r.appointment = { date, time, center }; notify(r, 'تم تأكيد موعدك', date + ' • ' + time + ' • ' + center); save();
  }
  function cancelAppointment(id) { const r = request(id); if (!r.appointment) throw Error('لا يوجد موعد لإلغائه.'); delete r.appointment; notify(r, 'تم إلغاء الموعد'); save(); }
  function pay(id, method) {
    const r = request(id); if (r.paid || ['rejected', 'completed'].includes(r.status)) throw Error('لا توجد رسوم قابلة للدفع لهذا الطلب.');
    if (!['محفظة تجريبية', 'بطاقة تجريبية', 'نقدًا في المركز'].includes(method)) throw Error('اختر طريقة الدفع.');
    r.paid = true; r.receipt = uid(); r.method = method; r.paidAt = now(); notify(r, 'تم تسجيل دفع تجريبي', r.receipt); save();
  }
  function transition(id, status, note) {
    const r = request(id);
    const allowed = { submitted: ['approved', 'needs-info', 'rejected'], approved: ['issued'], issued: ['completed'] };
    if (!allowed[r.status]?.includes(status)) throw Error('لا يمكن الانتقال إلى هذه الحالة من الحالة الحالية.');
    if (['needs-info', 'rejected'].includes(status) && !note?.trim()) throw Error('أدخل ملاحظات المراجعة أو سبب الرفض.');
    if (status === 'issued' && !r.paid) throw Error('يجب تسجيل سداد الرسوم قبل الإصدار.');
    if (status === 'issued') r.documentNumber = 'SAMPLE-' + uid();
    r.status = status; r.history.push({ status, note: note?.trim() || labels[status], at: now() });
    notify(r, 'تحديث الطلب: ' + labels[status], note || r.id); save();
  }
  const api = { get state() { return state; }, services, create, resubmit, book, cancelAppointment, pay, transition, reset() { state = seed(); save(); } };
  window.PortalDemo = api;
  const root = document.querySelector('[data-portal-demo]');
  if (!root) return;
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const money = n => Number(n).toLocaleString('ar-YE') + ' ر.ي';
  const dateLabel = d => new Date(d).toLocaleDateString('ar-YE');
  const statusBadge = r => '<span class="pd-badge pd-' + r.status + '">' + labels[r.status] + '</span>';
  const navItems = [['dashboard', 'نظرة عامة'], ['new', 'طلب جديد'], ['requests', 'طلباتي'], ['appointments', 'المواعيد'], ['payments', 'المدفوعات'], ['documents', 'وثائقي'], ['notifications', 'الإشعارات'], ['review', 'مراجعة الموظف'], ['issuance', 'الإصدار والتسليم']];
  const titles = Object.fromEntries(navItems);
  let selectedFiles = [];
  let previewUrls = [];
  let flash = '';
  let filterText = '';
  let filterStatus = '';
  function route() {
    const hash = window.location.hash.slice(1).split('/');
    return { screen: titles[hash[0]] || ['details', 'preview'].includes(hash[0]) ? hash[0] : (root.dataset.screen || 'dashboard'), id: hash[1] || root.dataset.requestId };
  }
  function go(screen, id = '') {
    const hash = '#' + screen + (id ? '/' + encodeURIComponent(id) : '');
    if (window.location.hash === hash) render(); else window.location.hash = hash;
  }
  const button = (text, screen, id = '', secondary = false) => '<a class="pd-button' + (secondary ? ' pd-secondary' : '') + '" href="#' + screen + (id ? '/' + encodeURIComponent(id) : '') + '">' + text + '</a>';
  const options = (items, selected = '') => items.map(([value, label]) => '<option value="' + esc(value) + '"' + (value === selected ? ' selected' : '') + '>' + esc(label) + '</option>').join('');
  function card(title, body) { return '<section class="pd-card"><h3>' + title + '</h3>' + body + '</section>'; }
  function table(rows, employee = false) {
    if (!rows.length) return '<div class="pd-empty"><h3>لا توجد طلبات مطابقة</h3><p>يمكنك تغيير البحث أو تقديم طلب تجريبي جديد.</p>' + button('تقديم طلب', 'new') + '</div>';
    return '<div class="pd-table-wrap"><table><caption class="pd-sr">قائمة الطلبات وحالتها ورسومها</caption><thead><tr><th>رقم الطلب / المواطن</th><th>الخدمة</th><th>التاريخ</th><th>الحالة</th><th>الرسوم</th><th>الإجراء</th></tr></thead><tbody>' + rows.map(r => '<tr><td><b dir="ltr">' + esc(r.id) + '</b><small>' + esc(r.name) + '</small></td><td>' + esc(findService(r.service)[1]) + '</td><td>' + dateLabel(r.created) + '</td><td>' + statusBadge(r) + '</td><td>' + money(findService(r.service)[2]) + '<small>' + (r.paid ? 'مدفوعة تجريبيًا' : 'غير مدفوعة') + '</small></td><td>' + button(employee ? 'مراجعة' : 'التفاصيل', employee ? 'review' : 'details', r.id, true) + '</td></tr>').join('') + '</tbody></table></div>';
  }
  function overview() {
    const counts = [state.requests.filter(r => !['completed', 'rejected'].includes(r.status)).length, state.requests.filter(r => r.status === 'completed').length, state.requests.filter(r => r.appointment && r.appointment.date >= today()).length, state.requests.filter(r => r.status === 'issued').length];
    return '<div class="pd-stats">' + ['طلبات نشطة', 'طلبات مكتملة', 'مواعيد قادمة', 'جاهزة للتسليم'].map((label, i) => '<div class="pd-stat"><strong>' + counts[i] + '</strong><span>' + label + '</span></div>').join('') + '</div><div class="pd-heading"><h2>خدماتك في مكان واحد</h2>' + button('تقديم طلب جديد +', 'new') + '</div><div class="pd-service-grid">' + [['identity', 'بطاقة الهوية', 'الإصدار لأول مرة والتجديد وبدل الفاقد', 'identity-first-issue'], ['passport', 'جواز السفر', 'طلبات إصدار وتجديد جواز السفر', 'passports-first-issue'], ['car', 'رخصة القيادة', 'طلبات إصدار وتجديد رخصة القيادة', 'driving-first-issue']].map(s => '<a class="pd-card pd-service" href="#new/' + s[3] + '"><span class="pd-icon">' + icon(s[0]) + '</span><h3>' + s[1] + '</h3><p>' + s[2] + '</p><b>بدء الخدمة ' + icon('arrow-left') + '</b></a>').join('') + '</div>' + card('آخر الطلبات', table(state.requests.slice(0, 5)));
  }
  const fileInput = '<label class="pd-upload">إرفاق المستندات والصورة الشخصية<input id="pd-files" type="file" accept=".jpg,.jpeg,.png,.pdf" multiple required><span>JPG، PNG، PDF • حتى 5 ملفات • 5 ميجابايت لكل ملف</span></label><div id="pd-file-list" class="pd-files" aria-live="polite"></div>';
  function applicationForm(id) {
    let service = id || new URLSearchParams(window.location.search).get('service') || '';
    const aliases = { 'driving-license-first': 'driving-first-issue', 'driving-license-renewal': 'driving-renewal', 'driving-first': 'driving-first-issue', 'identity-lost-replacement': 'identity-lost' };
    service = aliases[service] || service;
    return card('تقديم طلب خدمة', '<ol class="pd-steps"><li>اختيار الخدمة</li><li>البيانات والمرفقات</li><li>المراجعة والإرسال</li></ol><form id="pd-application"><div class="pd-form-grid"><label>الخدمة المطلوبة<select name="service" required><option value="">اختر الخدمة</option>' + options(services, service) + '</select></label><label>الاسم الكامل<input name="name" required maxlength="100" autocomplete="off" placeholder="استخدم اسمًا تجريبيًا"></label><label>الرقم الوطني التجريبي<input name="nationalId" inputmode="numeric" pattern="[0-9]{6,20}" required placeholder="مثال: 1000000001"></label><label>رقم الهاتف التجريبي<input name="phone" inputmode="numeric" pattern="[0-9]{7,15}" required placeholder="مثال: 777000001"></label></div><div id="pd-requirements" class="pd-notice"></div>' + fileInput + '<label class="pd-check"><input type="checkbox" required> راجعت البيانات والمرفقات وأقر بأنها بيانات للتجربة فقط.</label><p>لن تُرفع الملفات إلى خادم. تُحفظ أسماؤها وأحجامها فقط؛ المعاينة متاحة أثناء اختيارها.</p><button class="pd-button" type="submit">مراجعة وإرسال الطلب التجريبي</button></form>');
  }
  function details(id, employee = false) {
    const r = state.requests.find(x => x.id === id) || (employee && !id ? state.requests.find(x => x.status === 'submitted') || state.requests[0] : null);
    if (!r) return card('الطلب غير موجود', button('عرض الطلبات', employee ? 'review' : 'requests'));
    const info = '<div class="pd-heading"><div><h2 dir="ltr">' + esc(r.id) + '</h2><p>' + esc(findService(r.service)[1]) + '</p></div>' + statusBadge(r) + '</div><dl class="pd-info"><div><dt>المواطن</dt><dd>' + esc(r.name) + '</dd></div><div><dt>الرقم الوطني التجريبي</dt><dd>' + esc(r.nationalId) + '</dd></div><div><dt>الرسوم التجريبية</dt><dd>' + money(findService(r.service)[2]) + ' — ' + (r.paid ? 'مدفوعة' : 'غير مدفوعة') + '</dd></div><div><dt>الموعد</dt><dd>' + (r.appointment ? esc(r.appointment.date + ' • ' + r.appointment.time + ' • ' + r.appointment.center) : 'لم يُحجز بعد') + '</dd></div></dl>';
    const files = '<ul class="pd-list">' + r.files.map(f => '<li><span>' + esc(f.name) + '</span><small>' + Math.ceil(f.size / 1024) + ' KB • بيانات الملف فقط</small></li>').join('') + '</ul>';
    let action = '<div class="pd-actions">' + button('حجز / تعديل موعد', 'appointments', r.id, true) + (!r.paid && !['rejected', 'completed'].includes(r.status) ? button('دفع تجريبي', 'payments', r.id) : '') + (r.documentNumber ? button('معاينة الوثيقة', 'preview', r.id, true) : '') + '</div>';
    if (!employee && r.status === 'needs-info') action += card('استكمال المرفقات', '<p>' + esc(r.history[r.history.length - 1].note) + '</p><form id="pd-resubmit" data-id="' + esc(r.id) + '">' + fileInput + '<button class="pd-button">إعادة إرسال للمراجعة</button></form>');
    if (employee && ['submitted', 'approved', 'issued'].includes(r.status)) {
      const choices = r.status === 'submitted' ? [['approved', 'اعتماد الطلب'], ['needs-info', 'إعادة للاستكمال'], ['rejected', 'رفض الطلب']] : r.status === 'approved' ? [['issued', 'إصدار الوثيقة تجريبيًا']] : [['completed', 'تأكيد التسليم وإكمال الطلب']];
      action += card('قرار الموظف — محاكاة', '<form id="pd-review" data-id="' + esc(r.id) + '"><label>الإجراء<select name="status">' + options(choices) + '</select></label><label>ملاحظات المراجعة / سبب الرفض<textarea name="note" maxlength="500" rows="3"></textarea></label><p>الإصدار يتطلب تسجيل الدفع التجريبي. لا تُنشأ وثيقة رسمية.</p><button class="pd-button">حفظ الإجراء</button></form>');
    }
    return card('تفاصيل الطلب', info + action) + '<div class="pd-columns">' + card('المرفقات', files || '<p>لا توجد مرفقات</p>') + card('سجل تتبع الطلب', '<ol class="pd-timeline">' + r.history.map(h => '<li><b>' + labels[h.status] + '</b><p>' + esc(h.note) + '</p><small>' + dateLabel(h.at) + '</small></li>').join('') + '</ol>') + '</div>';
  }
  function appointments(id) {
    const active = state.requests.filter(r => !['completed', 'rejected'].includes(r.status));
    return card('حجز موعد أو تعديله', '<form id="pd-book"><div class="pd-form-grid"><label>الطلب<select name="id" required><option value="">اختر الطلب</option>' + options(active.map(r => [r.id, r.id + ' — ' + findService(r.service)[1]]), id) + '</select></label><label>مركز الخدمة<select name="center">' + options(['صنعاء', 'عدن', 'تعز'].map(x => [x, 'مركز ' + x])) + '</select></label><label>التاريخ<input type="date" name="date" min="' + today() + '" required></label><label>الوقت<select name="time">' + options(['09:00', '10:00', '11:00', '12:00'].map(x => [x, x])) + '</select></label></div><button class="pd-button">تأكيد الموعد التجريبي</button></form>') + card('مواعيدي', state.requests.filter(r => r.appointment).map(r => '<div class="pd-list-item"><div><b>' + esc(r.id) + '</b><p>' + esc(r.appointment.date + ' • ' + r.appointment.time + ' • مركز ' + r.appointment.center) + '</p></div><button class="pd-button pd-secondary" data-cancel="' + esc(r.id) + '">إلغاء الموعد</button></div>').join('') || '<div class="pd-empty">لا توجد مواعيد. اختر طلبًا لحجز موعد.</div>');
  }
  function payments(id) {
    const unpaid = state.requests.filter(r => !r.paid && !['rejected', 'completed'].includes(r.status));
    return card('تسجيل دفع تجريبي', '<p class="pd-notice">محاكاة فقط — لا تدخل بيانات بطاقة أو محفظة حقيقية، ولن يُخصم أي مبلغ.</p><form id="pd-pay"><div class="pd-form-grid"><label>الطلب والرسوم<select name="id" required><option value="">اختر الطلب</option>' + options(unpaid.map(r => [r.id, r.id + ' — ' + money(findService(r.service)[2])]), id) + '</select></label><label>طريقة الدفع<select name="method">' + options(['محفظة تجريبية', 'بطاقة تجريبية', 'نقدًا في المركز'].map(x => [x, x])) + '</select></label></div><button class="pd-button">تأكيد الدفع التجريبي</button></form>') + card('سجل الإيصالات', state.requests.filter(r => r.paid).map(r => '<div class="pd-list-item"><div><b>' + esc(r.receipt) + '</b><p>' + esc(r.id) + ' • ' + money(findService(r.service)[2]) + ' • ' + esc(r.method) + '</p><small>إيصال تجريبي غير صالح للاستخدام الرسمي</small></div>' + button('عرض الطلب', 'details', r.id, true) + '</div>').join('') || '<div class="pd-empty">لا توجد مدفوعات مسجلة.</div>');
  }
  function documents() {
    const rows = state.requests.filter(r => r.documentNumber);
    return card('وثائقي التجريبية', rows.map(r => '<div class="pd-list-item"><div><h3>' + esc(findService(r.service)[1]) + '</h3><p>' + esc(r.documentNumber) + '</p>' + statusBadge(r) + '</div>' + button('معاينة وطباعة', 'preview', r.id) + '</div>').join('') || '<div class="pd-empty">ستظهر الوثيقة بعد اعتماد الطلب وسداد الرسوم وإصداره من واجهة الموظف.</div>');
  }
  function preview(id) {
    const r = state.requests.find(x => x.id === id && x.documentNumber);
    if (!r) return card('لا توجد وثيقة لهذا الطلب', button('العودة للوثائق', 'documents'));
    return '<div class="pd-actions pd-no-print">' + button('العودة للوثائق', 'documents', '', true) + '<button class="pd-button" data-print>طباعة / حفظ PDF من المتصفح</button></div><article class="pd-document"><div class="pd-watermark">نموذج تجريبي</div><div class="pd-document-top">منصة الإصدار الآلي للوثائق الرسمية</div><h2>' + esc(findService(r.service)[1]) + '</h2><div class="pd-document-body"><div class="pd-photo">صورة<br>تجريبية</div><dl><dt>الاسم</dt><dd>' + esc(r.name) + '</dd><dt>الرقم الوطني التجريبي</dt><dd>' + esc(r.nationalId) + '</dd><dt>رقم النموذج</dt><dd dir="ltr">' + esc(r.documentNumber) + '</dd><dt>حالة الطلب</dt><dd>' + labels[r.status] + '</dd></dl></div><strong>للعرض الأكاديمي فقط — ليست وثيقة رسمية</strong></article>';
  }
  function render() {
    previewUrls.forEach(url => URL.revokeObjectURL(url)); previewUrls = []; selectedFiles = [];
    const { screen, id } = route();
    const bannerTitle = document.querySelector('[data-banner-title]');
    if (bannerTitle) {
      bannerTitle.textContent = titles[screen] || (screen === 'preview' ? 'معاينة الوثيقة' : 'تفاصيل الطلب');
    }
    const unread = state.notifications.filter(n => !n.read).length;
    root.innerHTML = '<div class="pd-shell"><header class="pd-hero"><p class="pd-eyebrow">بوابة الخدمات الإلكترونية</p><h1>' + (titles[screen] || (screen === 'preview' ? 'معاينة الوثيقة' : 'تفاصيل الطلب')) + '</h1><p>خدمات الهوية والجوازات ورخص القيادة في مكان واحد</p></header><div class="pd-layout"><aside class="pd-sidebar"><div class="pd-profile"><span class="pd-avatar">أم</span><b>أحمد محمد علي</b><small>حساب عرض تجريبي</small></div><nav aria-label="تنقل بوابة العرض">' + navItems.map(([key, label]) => '<a href="#' + key + '"' + (screen === key ? ' class="is-active" aria-current="page"' : '') + '>' + icon(navIcons[key]) + label + (key === 'notifications' && unread ? '<span>' + unread + '</span>' : '') + '</a>').join('') + '</nav><button class="pd-reset" data-reset>إعادة البيانات التجريبية</button><a class="pd-home" href="/">العودة للرئيسية ' + icon('arrow-left') + '</a></aside><div class="pd-content"><div class="pd-notice"><b>معاينة تفاعلية ببيانات تجريبية</b><span> لا يوجد إرسال أو دفع أو إصدار فعلي. استخدم بيانات غير حقيقية.</span></div><p id="pd-feedback" role="status" tabindex="-1" class="pd-feedback">' + esc(flash) + '</p><div id="pd-view"></div><p class="pd-storage">' + (storageAvailable ? 'تُحفظ التغييرات محليًا في هذا المتصفح.' : 'التخزين المحلي غير متاح؛ التغييرات لهذه الجلسة فقط.') + '</p></div></div></div>';
    const view = root.querySelector('#pd-view');
    switch (screen) {
      case 'new': view.innerHTML = applicationForm(id); break;
      case 'requests': case 'issuance': case 'review':
        if (screen === 'review' && id) { view.innerHTML = details(id, true); break; }
        view.innerHTML = '<div class="pd-heading"><h2>' + (screen === 'requests' ? 'سجل الطلبات' : screen === 'review' ? 'طلبات المراجعة' : 'الوثائق والإصدار والتسليم') + '</h2>' + button('طلب جديد +', 'new') + '</div><div class="pd-filters"><label>البحث<input id="pd-search" type="search" placeholder="رقم الطلب أو الاسم" value="' + esc(filterText) + '"></label><label>الحالة<select id="pd-status"><option value="">جميع الحالات</option>' + options(Object.entries(labels), filterStatus) + '</select></label></div><div id="pd-results"></div>'; break;
      case 'details': view.innerHTML = details(id); break;
      case 'appointments': view.innerHTML = appointments(id); break;
      case 'payments': view.innerHTML = payments(id); break;
      case 'documents': view.innerHTML = documents(); break;
      case 'preview': view.innerHTML = preview(id); break;
      case 'notifications': view.innerHTML = card('مركز الإشعارات', '<button class="pd-button pd-secondary" data-read>تحديد الكل كمقروء</button><ul class="pd-list">' + state.notifications.map(n => '<li class="' + (n.read ? '' : 'pd-unread') + '"><div><b>' + esc(n.title) + '</b><p>' + esc(n.text) + '</p><small>' + dateLabel(n.at) + '</small></div>' + (n.requestId ? button('عرض', 'details', n.requestId, true) : '') + '</li>').join('') + '</ul>'); break;
      default: view.innerHTML = overview();
    }
    bind(screen);
    if (flash) root.querySelector('#pd-feedback').focus({ preventScroll: true });
    flash = '';
  }
  function report(error) { const el = root.querySelector('#pd-feedback'); el.textContent = error.message || error; el.classList.add('pd-error'); el.focus(); }
  function act(fn, message, target) { try { fn(); flash = message; if (target) go(target.screen, target.id); else render(); } catch (e) { report(e); } }
  function bind(screen) {
    root.querySelector('[data-reset]').onclick = () => {
      if (window.confirm('إعادة جميع الطلبات والمواعيد والمدفوعات إلى بيانات البداية؟')) act(() => api.reset(), 'تمت إعادة البيانات التجريبية.', { screen: 'dashboard' });
    };
    const fileEl = root.querySelector('#pd-files');
    if (fileEl) fileEl.onchange = () => {
      previewUrls.forEach(url => URL.revokeObjectURL(url)); previewUrls = [];
      selectedFiles = Array.from(fileEl.files || []);
      const list = root.querySelector('#pd-file-list');
      try { validateFiles(selectedFiles); } catch (e) { selectedFiles = []; fileEl.value = ''; list.textContent = ''; report(e); return; }
      list.innerHTML = selectedFiles.map(f => {
        const url = URL.createObjectURL(f); previewUrls.push(url);
        return '<div class="pd-file">' + (f.type.startsWith('image/') ? '<img alt="معاينة المرفق" src="' + url + '">' : '<span class="pd-pdf">PDF</span>') + '<a href="' + url + '" target="_blank" rel="noopener">' + esc(f.name) + '</a><small>' + Math.ceil(f.size / 1024) + ' KB</small></div>';
      }).join('');
    };
    const form = root.querySelector('#pd-application');
    if (form) {
      const showRequirements = () => {
        const s = findService(form.elements.service.value);
        root.querySelector('#pd-requirements').textContent = s ? 'الرسوم التجريبية: ' + money(s[2]) + ' • أرفق صورة شخصية ومستند الإثبات المناسب للخدمة.' + (s[0].startsWith('driving') ? ' تشمل متطلبات الرخصة الفحص الطبي في النموذج الأكاديمي.' : '') : 'اختر الخدمة لعرض الرسوم ومتطلبات المرفقات.';
      };
      form.elements.service.onchange = showRequirements; showRequirements();
      form.onsubmit = e => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(form)); data.files = selectedFiles;
        if (!window.confirm('مراجعة الطلب قبل الإرسال:\n' + (findService(data.service)?.[1] || '') + '\nالاسم: ' + data.name + '\nعدد المرفقات: ' + selectedFiles.length + '\nهل تؤكد إرسال الطلب التجريبي؟')) return;
        act(() => { const r = create(data); go('details', r.id); }, 'تم تقديم الطلب التجريبي. يمكنك حجز موعد وتسجيل الدفع.');
      };
    }
    const resubmitForm = root.querySelector('#pd-resubmit');
    if (resubmitForm) resubmitForm.onsubmit = e => { e.preventDefault(); act(() => resubmit(resubmitForm.dataset.id, selectedFiles), 'أُعيد الطلب إلى المراجعة.'); };
    const bookForm = root.querySelector('#pd-book');
    if (bookForm) {
      const fillAppointment = () => {
        const existing = state.requests.find(r => r.id === bookForm.elements.id.value)?.appointment;
        bookForm.elements.date.value = existing?.date || '';
        if (existing) {
          bookForm.elements.time.value = existing.time;
          bookForm.elements.center.value = existing.center;
        }
      };
      bookForm.elements.id.onchange = fillAppointment;
      fillAppointment();
    }
    if (bookForm) bookForm.onsubmit = e => { e.preventDefault(); const d = Object.fromEntries(new FormData(bookForm)); act(() => book(d.id, d.date, d.time, d.center), 'تم تأكيد الموعد.'); };
    root.querySelectorAll('[data-cancel]').forEach(el => el.onclick = () => { if (window.confirm('هل تريد إلغاء هذا الموعد التجريبي؟')) act(() => cancelAppointment(el.dataset.cancel), 'تم إلغاء الموعد.'); });
    const payForm = root.querySelector('#pd-pay');
    if (payForm) payForm.onsubmit = e => { e.preventDefault(); const d = Object.fromEntries(new FormData(payForm)); if (window.confirm('تأكيد محاكاة الدفع؟ لن يتم خصم أي مبلغ.')) act(() => pay(d.id, d.method), 'تم تسجيل الدفع وإضافة الإيصال التجريبي.'); };
    const reviewForm = root.querySelector('#pd-review');
    if (reviewForm) reviewForm.onsubmit = e => { e.preventDefault(); const d = Object.fromEntries(new FormData(reviewForm)); act(() => transition(reviewForm.dataset.id, d.status, d.note), 'تم تحديث الحالة وإرسال إشعار تجريبي.'); };
    const readButton = root.querySelector('[data-read]');
    if (readButton) readButton.onclick = () => act(() => { state.notifications.forEach(n => n.read = true); save(); }, 'تم تعليم الإشعارات كمقروءة.');
    const printButton = root.querySelector('[data-print]');
    if (printButton) printButton.onclick = () => window.print();
    const results = root.querySelector('#pd-results');
    if (results) {
      const update = () => {
        filterText = root.querySelector('#pd-search').value; filterStatus = root.querySelector('#pd-status').value;
        results.innerHTML = table(state.requests.filter(r => (!filterStatus || r.status === filterStatus) && (r.id + ' ' + r.name).toLowerCase().includes(filterText.toLowerCase()) && (screen !== 'issuance' || ['approved', 'issued', 'completed'].includes(r.status))), screen !== 'requests');
      };
      root.querySelector('#pd-search').oninput = update; root.querySelector('#pd-status').onchange = update; update();
    }
  }
  window.addEventListener('hashchange', render);
  window.addEventListener('storage', e => { if (e.key === KEY) window.location.reload(); });
  render();
})();

