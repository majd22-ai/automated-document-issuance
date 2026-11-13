(() => {
  'use strict';
  const labels = {
    male: 'ذكر', female: 'أنثى', unspecified: 'غير محدد', single: 'أعزب', married: 'متزوج', divorced: 'مطلق', widowed: 'أرمل',
    active: 'نشط', dissolved: 'منتهٍ', cancelled: 'ملغى', corrected: 'مصَحّح', pending: 'قيد الانتظار', released: 'مرفوع', former: 'سابق', deceased: 'متوفى',
    child: 'الابن / الابنة', father: 'الأب', mother: 'الأم', husband: 'الزوج', wife: 'الزوجة', holder: 'صاحب البطاقة', head: 'رب الأسرة', subject: 'الشخص المعني', spouse: 'زوج / زوجة', parent: 'والد', sibling: 'أخ / أخت', other: 'أخرى',
    child_name: 'اسم المولود', gender: 'الجنس', father_name: 'اسم الأب', mother_name: 'اسم الأم', birth_certificate_number: 'رقم شهادة الميلاد التجريبي', registered_on: 'تاريخ التسجيل',
    contract_number: 'رقم عقد الزواج التجريبي', wife_identifier: 'معرف الزوجة التجريبي', divorce_type: 'نوع الطلاق', document_number: 'رقم الوثيقة التجريبية', certificate_number: 'رقم الشهادة التجريبية', cause: 'السبب التجريبي',
    card_number: 'رقم البطاقة التجريبي', issued_on: 'تاريخ الإصدار', expires_on: 'تاريخ الانتهاء', issuer: 'جهة الإصدار التجريبية', family_number: 'رقم الأسرة التجريبي',
    request_number: 'رقم طلب التصحيح', field: 'البيان المصحح', previous_value: 'القيمة السابقة', new_value: 'القيمة الجديدة', reason: 'سبب التصحيح', restriction_type: 'نوع القيد', description: 'وصف القيد', authority: 'الجهة التجريبية المسجلة',
  };
  window.demoCivilLabel = value => labels[value] || value;
  const fields = {
    birth: ['child_name', 'gender', 'father_name', 'mother_name', 'birth_certificate_number', 'registered_on'],
    marriage: ['contract_number', 'wife_identifier'], divorce: ['divorce_type', 'document_number'],
    death: ['certificate_number', 'cause'], identity_card: ['card_number', 'issued_on', 'expires_on', 'issuer'],
    family: ['family_number'], correction: ['request_number', 'field', 'previous_value', 'new_value', 'reason'],
    restriction: ['restriction_type', 'description', 'authority'],
  };
  const roles = { birth: ['child', 'father', 'mother'], marriage: ['husband', 'wife'], divorce: ['husband', 'wife'], death: ['deceased'], identity_card: ['holder'], family: ['head'], correction: ['subject'], restriction: ['subject'] };
  document.querySelectorAll('form[data-demo-record-form]').forEach(form => {
    const type = form.elements.record_type;
    const details = form.elements.details_json;
    const parties = form.elements.parties_json;
    let savedDetails;
    let savedParties;
    try { savedDetails = JSON.parse(details.value || '{}'); savedParties = JSON.parse(parties.value || '[]'); }
    catch { return; }
    const panel = document.createElement('div');
    panel.className = 'demo-admin-form demo-admin-wide';
    details.parentElement.hidden = true;
    parties.parentElement.hidden = true;
    parties.required = false;
    form.insertBefore(panel, details.parentElement);
    let controls = [];
    let partyControls = [];
    const draw = () => {
      panel.replaceChildren();
      controls = [];
      partyControls = [];
      (fields[type.value] || []).forEach(key => {
        const label = document.createElement('label');
        label.textContent = labels[key];
        const input = document.createElement('input');
        input.type = key.endsWith('_on') ? 'date' : 'text';
        input.value = savedDetails[key] ?? '';
        input.maxLength = 500;
        label.append(input);
        panel.append(label);
        controls.push({key, input});
      });
      (roles[type.value] || []).forEach(role => {
        const label = document.createElement('label');
        label.textContent = labels[role];
        const select = form.elements.person_id.cloneNode(true);
        select.removeAttribute('name');
        select.removeAttribute('id');
        select.required = !['father', 'mother'].includes(role);
        const empty = document.createElement('option');
        empty.value = ''; empty.textContent = 'اختر مواطناً تجريبياً — غير مدرج إن تُرك فارغاً';
        select.prepend(empty);
        select.value = String(savedParties.find(party => party.role === role)?.person_id || '');
        label.append(select);
        panel.append(label);
        partyControls.push({role, select});
      });
    };
    draw();
    type.addEventListener('change', () => { savedDetails = {}; savedParties = []; draw(); });
    form.addEventListener('submit', () => {
      const values = {...savedDetails};
      controls.forEach(({key, input}) => { if (input.value) values[key] = input.value; else delete values[key]; });
      details.value = JSON.stringify(values);
      parties.value = JSON.stringify(partyControls.filter(({select}) => select.value).map(({role, select}) => ({role, person_id: Number(select.value)})));
    });
  });
})();
