/**
 * simulation-modals.js
 * مكتبة مشتركة لنوافذ المحاكاة المنبثقة
 * تُستخدم في: بصمة الأصابع، التحقق الإلكتروني، السجل المدني، مصادقة الهوية
 */
(() => {
  'use strict';

  /* ─────────────────────────────────────────────
     helpers
  ───────────────────────────────────────────── */
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

  /** فتح dialog بـ showModal + trap focus */
  function openModal(dialog) {
    if (!dialog || dialog.open) return;
    dialog.showModal();
    // focus أول عنصر يمكن التفاعل معه
    const focusable = dialog.querySelector(
      'button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex="0"]'
    );
    if (focusable) setTimeout(() => focusable.focus(), 50);
  }

  /** إغلاق Dialog */
  function closeModal(dialog) {
    if (dialog && dialog.open) dialog.close();
  }

  /** فتح/إغلاق بـ Escape تلقائياً (مدمج في <dialog>) */
  /** إنشاء عنصر نصي */
  function el(tag, attrs = {}, text = '') {
    const e = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => {
      if (k === 'class') e.className = v;
      else if (k === 'style') e.style.cssText = v;
      else e.setAttribute(k, v);
    });
    if (text) e.textContent = text;
    return e;
  }

  /** تنظيف محتوى عنصر */
  function clear(el) { el.replaceChildren(); return el; }

  /** عرض حالة تحميل */
  function showLoading(container, msg = 'جارٍ تنفيذ العملية...') {
    clear(container);
    const wrap = el('div', { class: 'sim-loading-state' });
    wrap.append(el('div', { class: 'sim-spinner', role: 'status', 'aria-label': 'جارٍ التحميل' }));
    wrap.append(el('p', { class: 'sim-loading-text' }, msg));
    container.append(wrap);
  }

  /** عرض حالة نجاح */
  function showSuccess(container, title, detail = '') {
    clear(container);
    const wrap = el('div', { class: 'sim-success-state', role: 'status' });
    wrap.append(el('span', { class: 'sim-success-icon', 'aria-hidden': 'true' }, '✓'));
    wrap.append(el('p', { class: 'sim-success-title' }, title));
    if (detail) wrap.append(el('p', { style: 'color:#8a99a8;font-size:.88rem;line-height:1.75;margin:8px 0 0' }, detail));
    container.append(wrap);
  }

  /** عرض حالة فشل */
  function showFailure(container, title, detail = '') {
    clear(container);
    const wrap = el('div', { class: 'sim-failure-state', role: 'alert' });
    wrap.append(el('span', { class: 'sim-failure-icon', 'aria-hidden': 'true' }, '✗'));
    wrap.append(el('p', { class: 'sim-failure-title' }, title));
    if (detail) wrap.append(el('p', { style: 'color:#8a99a8;font-size:.88rem;line-height:1.75;margin:8px 0 0' }, detail));
    container.append(wrap);
  }

  /** إرسال FormData عبر fetch وإرجاع JSON */
  async function postForm(url, formData) {
    const res = await fetch(url, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    const json = await res.json().catch(() => ({}));
    return { ok: res.ok, status: res.status, json };
  }

  /** استخراج أولى رسائل الخطأ من payload */
  function extractErrors(json) {
    if (json.errors) return Object.values(json.errors).flat().join(' | ');
    return json.message || 'حدث خطأ غير متوقع.';
  }

  /* ─────────────────────────────────────────────
     1. محاكاة السجل المدني
        trigger: [data-civil-demo-open]
        modal:   #sim-civil-modal
  ───────────────────────────────────────────── */
  function initCivilDemo() {
    const modal = $('#sim-civil-modal');
    const trigger = $('[data-civil-demo-open]');
    const form  = $('#sim-civil-form');
    const bodyEl = modal ? $('.sim-modal-body', modal) : null;
    const resultZone = modal ? $('#sim-civil-result', modal) : null;
    if (!modal || !trigger || !form) return;

    // فتح
    trigger.addEventListener('click', () => {
      // مسح أي نتيجة سابقة
      if (resultZone) clear(resultZone);
      // إعادة تفعيل النموذج إن كان مخفياً
      form.style.display = '';
      openModal(modal);
    });

    // إغلاق
    $$('[data-sim-close]', modal).forEach(btn => btn.addEventListener('click', () => closeModal(modal)));

    // إرسال
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('[type="submit"]');
      btn.disabled = true;

      if (resultZone) {
        showLoading(resultZone, 'جارٍ الاستعلام عن السجل المدني...');
      }

      const formData = new FormData(form);
      const url = form.action;

      try {
        const { ok, json } = await postForm(url, formData);
        if (!ok) {
          const errMsg = extractErrors(json);
          if (resultZone) showFailure(resultZone, 'فشل الاستعلام', errMsg);
          // عرض أخطاء الحقول
          showCivilFieldErrors(modal, json.errors || {});
        } else {
          // النجاح: عرض نتيجة المحاكاة
          const outcome = json.outcome || json.result || 'unknown';
          const outcomeLabel = {
            match:       '✓ تطابقت البيانات مع سجلات قاعدة البيانات التجريبية.',
            mismatch:    '✗ البيانات المُدخلة لا تطابق ما في السجل.',
            not_found:   '⚠ لم يُعثر على رقم هوية بهذا الرقم.',
            unavailable: '⚠ خدمة السجل المدني غير متاحة حالياً.',
          }[outcome] || '— نتيجة غير معروفة.';

          const isGood = outcome === 'match';
          if (resultZone) {
            if (isGood) showSuccess(resultZone, 'تمّ التحقق بنجاح', outcomeLabel);
            else showFailure(resultZone, 'نتيجة التحقق', outcomeLabel);
          }
        }
      } catch {
        if (resultZone) showFailure(resultZone, 'خطأ في الاتصال', 'لم يتمكن النظام من الوصول إلى الخادم. تحقق من الاتصال وأعد المحاولة.');
      } finally {
        btn.disabled = false;
      }
    });
  }

  function showCivilFieldErrors(modal, errors) {
    // مسح الأخطاء القديمة
    $$('.sim-field-error', modal).forEach(e => e.remove());
    Object.entries(errors).forEach(([field, msgs]) => {
      const input = $(`[name="${field}"]`, modal);
      if (!input) return;
      const errEl = el('p', { class: 'sim-field-error', role: 'alert' }, Array.isArray(msgs) ? msgs[0] : msgs);
      input.insertAdjacentElement('afterend', errEl);
    });
  }

  /* ─────────────────────────────────────────────
     2. بدء التحقق الإلكتروني
        trigger: [data-verify-open="{kind}"]
        modal:   #sim-verify-modal-{kind}
  ───────────────────────────────────────────── */
  function initElectronicVerification() {
    $$('[data-verify-open]').forEach(trigger => {
      const kind = trigger.dataset.verifyOpen;
      const modal = $(`#sim-verify-modal-${kind}`);
      if (!modal) return;

      trigger.addEventListener('click', () => openModal(modal));
      $$('[data-sim-close]', modal).forEach(btn => btn.addEventListener('click', () => closeModal(modal)));

      const form = $('form[data-verify-form]', modal);
      if (!form) return;

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const stateZone = $('#sim-verify-state', modal);
        const actionZone = $('#sim-verify-actions', modal);
        const submitBtn  = form.querySelector('[type="submit"]');
        submitBtn.disabled = true;
        if (actionZone) actionZone.style.display = 'none';

        if (stateZone) showLoading(stateZone, 'جارٍ تشغيل عملية التحقق...');

        const formData = new FormData(form);
        // تجديد مفتاح idempotency لمنع التكرار
        const uuidInput = form.querySelector('[name="idempotency_key"]');
        if (uuidInput) uuidInput.value = crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36);

        try {
          const { ok, json } = await postForm(form.action, formData);
          if (!ok) {
            const errMsg = extractErrors(json);
            if (stateZone) showFailure(stateZone, 'فشل بدء التحقق', errMsg);
          } else {
            if (stateZone) showSuccess(stateZone, 'بدأت عملية التحقق', 'تمّ إرسال طلب التحقق. ستظهر النتيجة في سجل المحاولات بعد اكتمال المعالجة.');
          }
          // زر إغلاق
          const closeBtn = el('button', { class: 'sim-btn sim-btn-outline', type: 'button' }, 'إغلاق');
          closeBtn.addEventListener('click', () => {
            closeModal(modal);
            // تحديث الصفحة لعرض السجل الجديد
            if (ok) window.location.reload();
          });
          modal.querySelector('.sim-modal-body').append(closeBtn);
        } catch {
          if (stateZone) showFailure(stateZone, 'خطأ في الاتصال', 'لم يتمكن النظام من الاتصال بالخادم.');
          submitBtn.disabled = false;
          if (actionZone) actionZone.style.display = '';
        }
      });
    });
  }

  /* ─────────────────────────────────────────────
     3. بصمة الأصابع (جهاز القياس)
        trigger: [data-fp-open]
        modal:   #sim-fp-modal
  ───────────────────────────────────────────── */
  function initFingerprintModal() {
    const modal   = $('#sim-fp-modal');
    const trigger = $('[data-fp-open]');
    if (!modal || !trigger) return;

    const fpSvg     = $('.sim-fingerprint-svg', modal);
    const fpState   = $('#sim-fp-state', modal);
    const fpProgress = $('#sim-fp-progress', modal);
    const fpResult  = $('#sim-fp-result', modal);
    const fpForm    = $('form[data-fp-form]', modal);
    const startBtn  = $('[data-fp-start]', modal);
    let running = false;

    trigger.addEventListener('click', () => openModal(modal));
    $$('[data-sim-close]', modal).forEach(btn => btn.addEventListener('click', () => {
      if (!running) closeModal(modal);
    }));

    if (!fpForm) return;

    fpForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (running || startBtn.disabled) return;
      running = true;
      startBtn.disabled = true;
      if (fpSvg)     { fpSvg.classList.remove('is-success','is-failure'); fpSvg.classList.add('is-running'); }
      if (fpState)   fpState.textContent = 'جارٍ تشغيل جهاز بصمة الأصابع...';
      if (fpProgress) fpProgress.value = 25;
      if (fpResult)  clear(fpResult);

      const formData = new FormData(fpForm);
      let completed = false;

      try {
        const { ok, json } = await postForm(fpForm.action, formData);

        if (!ok) {
          const msg = extractErrors(json);
          if (fpSvg)   { fpSvg.classList.remove('is-running'); fpSvg.classList.add('is-failure'); }
          if (fpState) fpState.textContent = 'فشلت عملية البصمة.';
          if (fpResult) showFailure(fpResult, 'خطأ في التسجيل', msg);
          if (fpProgress) fpProgress.value = 0;
        } else {
          completed = true;
          if (fpProgress) fpProgress.value = 100;
          const success = json.result === 'success';
          if (fpSvg) { fpSvg.classList.remove('is-running'); fpSvg.classList.add(success ? 'is-success' : 'is-failure'); }
          if (fpState) fpState.textContent = success ? 'تمّ التقاط بصمة الأصابع بنجاح' : 'فشل التقاط البصمة — يمكن إعادة المحاولة';

          if (fpResult) {
            if (success) {
              showSuccess(fpResult, 'تمّ تسجيل البصمة', `المرجع: ${json.reference || '—'} | النتيجة: ${json.result_label || '—'}`);
            } else {
              showFailure(fpResult, 'لم تنجح عملية البصمة', `${json.result_label || ''} — ${json.error_detail || ''}`);
            }
            // رابط العودة
            let redirectUrl = json.redirect_url;
            try { redirectUrl = new URL(redirectUrl, window.location.href); } catch { redirectUrl = null; }
            if (redirectUrl && redirectUrl.origin === window.location.origin) {
              const link = el('a', { class: 'sim-btn sim-btn-outline', href: redirectUrl.href, style: 'margin-top:12px;display:inline-flex' }, 'العودة إلى صفحة الطلب');
              fpResult.append(link);
            }
          }
        }
      } catch {
        if (fpSvg)   { fpSvg.classList.remove('is-running'); fpSvg.classList.add('is-failure'); }
        if (fpState) fpState.textContent = 'خطأ في الاتصال. يرجى المحاولة مجدداً.';
        if (fpResult) showFailure(fpResult, 'خطأ في الاتصال', 'تعذّر الوصول إلى الخادم، تحقق من الشبكة وأعد المحاولة.');
        if (fpProgress) fpProgress.value = 0;
      } finally {
        running = false;
        if (fpSvg) fpSvg.classList.remove('is-running');
        startBtn.disabled = completed;
        startBtn.textContent = completed ? 'تمّ التسجيل' : 'إعادة المحاولة';
      }
    });
  }

  /* ─────────────────────────────────────────────
     4. استقبال طلب بصمة (الحضور + الأمني)
        trigger: [data-arrival-open]
        modal:   #sim-arrival-modal
        + trigger: [data-security-open]
        + modal:   #sim-security-modal
  ───────────────────────────────────────────── */
  function initArrivalModals() {
    // --- نافذة تسجيل الحضور ---
    const arrModal   = $('#sim-arrival-modal');
    const arrTrigger = $('[data-arrival-open]');
    if (arrModal && arrTrigger) {
      arrTrigger.addEventListener('click', () => openModal(arrModal));
      $$('[data-sim-close]', arrModal).forEach(btn => btn.addEventListener('click', () => closeModal(arrModal)));

      const arrForm = $('form[data-arrival-form]', arrModal);
      if (arrForm) {
        arrForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          const stateZone = $('#sim-arrival-state', arrModal);
          const submitBtn = arrForm.querySelector('[type="submit"]');
          submitBtn.disabled = true;
          if (stateZone) showLoading(stateZone, 'جارٍ تسجيل حضور المواطن...');

          try {
            const { ok, json } = await postForm(arrForm.action, new FormData(arrForm));
            if (!ok) {
              const errMsg = extractErrors(json);
              if (stateZone) showFailure(stateZone, 'فشل تسجيل الحضور', errMsg);
              submitBtn.disabled = false;
            } else {
              if (stateZone) showSuccess(stateZone, 'تمّ تسجيل الحضور بنجاح', 'يمكن الآن توجيه المواطن إلى جهاز أخذ البصمة.');
              arrForm.style.display = 'none';
              const closeBtn = el('button', { class: 'sim-btn sim-btn-outline', type: 'button' }, 'إغلاق وتحديث الصفحة');
              closeBtn.addEventListener('click', () => { closeModal(arrModal); window.location.reload(); });
              arrModal.querySelector('.sim-modal-body').append(closeBtn);
            }
          } catch {
            if (stateZone) showFailure(stateZone, 'خطأ في الاتصال', 'تعذّر الوصول إلى الخادم.');
            submitBtn.disabled = false;
          }
        });
      }
    }

    // --- نافذة الإجراء الأمني ---
    const secModal   = $('#sim-security-modal');
    const secTrigger = $('[data-security-open]');
    if (secModal && secTrigger) {
      secTrigger.addEventListener('click', () => openModal(secModal));
      $$('[data-sim-close]', secModal).forEach(btn => btn.addEventListener('click', () => closeModal(secModal)));

      const secForm = $('form[data-security-form]', secModal);
      if (secForm) {
        secForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          const stateZone = $('#sim-security-state', secModal);
          const submitBtn = secForm.querySelector('[type="submit"]');
          submitBtn.disabled = true;
          if (stateZone) showLoading(stateZone, 'جارٍ تسجيل الإجراء الأمني...');

          try {
            const { ok, json } = await postForm(secForm.action, new FormData(secForm));
            if (!ok) {
              const errMsg = extractErrors(json);
              if (stateZone) showFailure(stateZone, 'فشل تسجيل الإجراء', errMsg);
              submitBtn.disabled = false;
            } else {
              if (stateZone) showSuccess(stateZone, 'تمّ تسجيل الإجراء الأمني', 'يمكن الآن متابعة إجراءات الإصدار.');
              secForm.style.display = 'none';
              const closeBtn = el('button', { class: 'sim-btn sim-btn-outline', type: 'button' }, 'إغلاق وتحديث الصفحة');
              closeBtn.addEventListener('click', () => { closeModal(secModal); window.location.reload(); });
              secModal.querySelector('.sim-modal-body').append(closeBtn);
            }
          } catch {
            if (stateZone) showFailure(stateZone, 'خطأ في الاتصال', 'تعذّر الوصول إلى الخادم.');
            submitBtn.disabled = false;
          }
        });
      }
    }

    // --- نافذة إعادة الإدراج في القائمة ---
    const requeueModal   = $('#sim-requeue-modal');
    const requeueTrigger = $('[data-requeue-open]');
    if (requeueModal && requeueTrigger) {
      requeueTrigger.addEventListener('click', () => openModal(requeueModal));
      $$('[data-sim-close]', requeueModal).forEach(btn => btn.addEventListener('click', () => closeModal(requeueModal)));

      const requeueForm = $('form[data-requeue-form]', requeueModal);
      if (requeueForm) {
        requeueForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          const stateZone = $('#sim-requeue-state', requeueModal);
          const submitBtn = requeueForm.querySelector('[type="submit"]');
          submitBtn.disabled = true;
          if (stateZone) showLoading(stateZone, 'جارٍ إعادة الطلب إلى قائمة الانتظار...');

          try {
            const { ok, json } = await postForm(requeueForm.action, new FormData(requeueForm));
            if (!ok) {
              if (stateZone) showFailure(stateZone, 'فشلت العملية', extractErrors(json));
              submitBtn.disabled = false;
            } else {
              if (stateZone) showSuccess(stateZone, 'تمّت إعادة الطلب بنجاح');
              requeueForm.style.display = 'none';
              const closeBtn = el('button', { class: 'sim-btn sim-btn-outline', type: 'button' }, 'إغلاق وتحديث');
              closeBtn.addEventListener('click', () => { closeModal(requeueModal); window.location.reload(); });
              requeueModal.querySelector('.sim-modal-body').append(closeBtn);
            }
          } catch {
            if (stateZone) showFailure(stateZone, 'خطأ في الاتصال', 'تعذّر الوصول إلى الخادم.');
            submitBtn.disabled = false;
          }
        });
      }
    }
  }

  /* ─────────────────────────────────────────────
     5. مصادقة الهوية (قبول / رفض)
        trigger: [data-attest-approve-open] / [data-attest-reject-open]
        modal:   #sim-attest-approve-modal / #sim-attest-reject-modal
  ───────────────────────────────────────────── */
  function initAttestationModals() {
    // --- نافذة القبول ---
    const approveModal   = $('#sim-attest-approve-modal');
    const approveTrigger = $('[data-attest-approve-open]');
    if (approveModal && approveTrigger) {
      approveTrigger.addEventListener('click', () => openModal(approveModal));
      $$('[data-sim-close]', approveModal).forEach(btn => btn.addEventListener('click', () => closeModal(approveModal)));

      const approveForm = $('form[data-attest-approve-form]', approveModal);
      if (approveForm) {
        approveForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          const stateZone = $('#sim-attest-approve-state', approveModal);
          const submitBtn = approveForm.querySelector('[type="submit"]');
          submitBtn.disabled = true;
          if (stateZone) showLoading(stateZone, 'جارٍ تسجيل قرار القبول...');

          try {
            const { ok, json } = await postForm(approveForm.action, new FormData(approveForm));
            if (!ok) {
              if (stateZone) showFailure(stateZone, 'فشل تسجيل القرار', extractErrors(json));
              submitBtn.disabled = false;
            } else {
              if (stateZone) showSuccess(stateZone, 'تمّت المصادقة بنجاح', 'تمّ تسجيل قرار القبول في النظام.');
              approveForm.style.display = 'none';
              const closeBtn = el('button', { class: 'sim-btn sim-btn-secondary', type: 'button' }, 'إغلاق وتحديث الصفحة');
              closeBtn.addEventListener('click', () => { closeModal(approveModal); window.location.reload(); });
              approveModal.querySelector('.sim-modal-body').append(closeBtn);
            }
          } catch {
            if (stateZone) showFailure(stateZone, 'خطأ في الاتصال', 'تعذّر الوصول إلى الخادم.');
            submitBtn.disabled = false;
          }
        });
      }
    }

    // --- نافذة الرفض ---
    const rejectModal   = $('#sim-attest-reject-modal');
    const rejectTrigger = $('[data-attest-reject-open]');
    if (rejectModal && rejectTrigger) {
      rejectTrigger.addEventListener('click', () => openModal(rejectModal));
      $$('[data-sim-close]', rejectModal).forEach(btn => btn.addEventListener('click', () => closeModal(rejectModal)));

      const rejectForm = $('form[data-attest-reject-form]', rejectModal);
      if (rejectForm) {
        rejectForm.addEventListener('submit', async (e) => {
          e.preventDefault();
          const stateZone = $('#sim-attest-reject-state', rejectModal);
          const submitBtn = rejectForm.querySelector('[type="submit"]');
          submitBtn.disabled = true;
          if (stateZone) showLoading(stateZone, 'جارٍ تسجيل قرار الرفض...');

          try {
            const { ok, json } = await postForm(rejectForm.action, new FormData(rejectForm));
            if (!ok) {
              if (stateZone) showFailure(stateZone, 'فشل تسجيل القرار', extractErrors(json));
              submitBtn.disabled = false;
            } else {
              if (stateZone) showSuccess(stateZone, 'تمّ تسجيل الرفض', 'تمّ تسجيل قرار الرفض في النظام وإشعار الجهة المعنية.');
              rejectForm.style.display = 'none';
              const closeBtn = el('button', { class: 'sim-btn sim-btn-outline', type: 'button' }, 'إغلاق وتحديث الصفحة');
              closeBtn.addEventListener('click', () => { closeModal(rejectModal); window.location.reload(); });
              rejectModal.querySelector('.sim-modal-body').append(closeBtn);
            }
          } catch {
            if (stateZone) showFailure(stateZone, 'خطأ في الاتصال', 'تعذّر الوصول إلى الخادم.');
            submitBtn.disabled = false;
          }
        });
      }
    }
  }

  /* ─────────────────────────────────────────────
     6. نافذة القبول المنفردة للأصالة (authority != witnesses)
        تعمل مع: [data-attest-single-approve] / [data-attest-single-reject]
  ───────────────────────────────────────────── */
  function initSingleAttestationModals() {
    // هذه تُقرأ من initAttestationModals نفسها — تُغطّيها المُعرّفات الجديدة
    // لكن نتعامل معها باختيار عام
    $$('[data-attest-decide-modal]').forEach(modal => {
      const key = modal.dataset.attestDecideModal;
      const trigger = $(`[data-attest-decide-open="${key}"]`);
      if (!trigger) return;
      trigger.addEventListener('click', () => openModal(modal));
      $$('[data-sim-close]', modal).forEach(btn => btn.addEventListener('click', () => closeModal(modal)));

      const form = $('form', modal);
      if (!form) return;
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const stateZone = $('.sim-modal-state', modal);
        const submitBtn = form.querySelector('[type="submit"]');
        submitBtn.disabled = true;
        if (stateZone) showLoading(stateZone, 'جارٍ تنفيذ العملية...');
        try {
          const { ok, json } = await postForm(form.action, new FormData(form));
          if (!ok) {
            if (stateZone) showFailure(stateZone, 'فشلت العملية', extractErrors(json));
            submitBtn.disabled = false;
          } else {
            if (stateZone) showSuccess(stateZone, 'تمّت العملية بنجاح');
            form.style.display = 'none';
            const closeBtn = el('button', { class: 'sim-btn sim-btn-outline', type: 'button' }, 'إغلاق وتحديث');
            closeBtn.addEventListener('click', () => { closeModal(modal); window.location.reload(); });
            modal.querySelector('.sim-modal-body').append(closeBtn);
          }
        } catch {
          if (stateZone) showFailure(stateZone, 'خطأ في الاتصال', 'تعذّر الوصول إلى الخادم.');
          submitBtn.disabled = false;
        }
      });
    });
  }

  /* ─────────────────────────────────────────────
     تهيئة الكل عند جاهزية DOM
  ───────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    initCivilDemo();
    initElectronicVerification();
    initFingerprintModal();
    initArrivalModals();
    initAttestationModals();
    initSingleAttestationModals();
  });

})();
