/* ===== Acharya Dr. Rajnath Jha — Site Engine (v2) ===== */
(function () {
  'use strict';

  /* ---------- CMS: content overrides from Admin Panel ---------- */
  var CMS_KEY = 'arj_content';
  function getCMS() {
    try { return JSON.parse(localStorage.getItem(CMS_KEY) || '{}'); } catch (e) { return {}; }
  }
  function applyCMS() {
    var data = getCMS();
    Object.keys(data).forEach(function (key) {
      var val = data[key];
      if (val === undefined || val === null || val === '') return;
      document.querySelectorAll('[data-cms="' + key + '"]').forEach(function (el) {
        el.innerHTML = val;
      });
      // href-bound fields (phone / email / whatsapp)
      document.querySelectorAll('[data-cms-href="' + key + '"]').forEach(function (el) {
        if (key === 'email') { el.setAttribute('href', 'mailto:' + val); if (el.textContent.indexOf('@') > -1) el.textContent = val; }
        else if (key.indexOf('phone') === 0) { el.setAttribute('href', 'tel:' + val.replace(/\s/g, '')); if (/^[+\d][\d\s-]{5,}$/.test(el.textContent.trim())) el.textContent = val; }
      });
    });
  }

  /* ---------- Enquiries store (read by Admin Panel) ---------- */
  window.ARJ_saveEnquiry = function (rec) {
    try {
      var list = JSON.parse(localStorage.getItem('arj_enquiries') || '[]');
      rec.id = 'ENQ' + Date.now();
      rec.date = new Date().toISOString();
      rec.status = 'New';
      rec.source = rec.source || 'Website';
      list.unshift(rec);
      localStorage.setItem('arj_enquiries', JSON.stringify(list));
    } catch (e) {}
  };

  /* ---------- Navigation ---------- */
  function initNav() {
    var mobileMenuBtn = document.getElementById('mobile-menu-btn');
    var mobileMenu = document.getElementById('mobile-menu');
    if (mobileMenuBtn && mobileMenu) {
      mobileMenuBtn.addEventListener('click', function () {
        mobileMenu.classList.toggle('hidden');
        mobileMenu.classList.toggle('flex');
      });
    }
    var navbar = document.getElementById('navbar');
    if (navbar) {
      window.addEventListener('scroll', function () {
        if (window.scrollY > 100) navbar.classList.add('scrolled');
        else navbar.classList.remove('scrolled');
      });
    }
    // Smooth scroll for on-page anchors
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
      anchor.addEventListener('click', function (e) {
        var href = this.getAttribute('href');
        if (href.length < 2) return;
        var target = document.querySelector(href);
        if (target) {
          e.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
          if (mobileMenu) { mobileMenu.classList.add('hidden'); mobileMenu.classList.remove('flex'); }
        }
      });
    });
  }
  window.toggleMobileDropdown = function (id) {
    var dropdown = document.getElementById(id);
    var arrow = document.getElementById(id + '-arrow');
    if (dropdown) dropdown.classList.toggle('show');
    if (arrow) arrow.classList.toggle('rotate-180');
  };

  /* ---------- Fade-in animation ---------- */
  function initFadeIn() {
    var els = document.querySelectorAll('.fade-in');
    if (!('IntersectionObserver' in window)) { els.forEach(function (el) { el.classList.add('visible'); }); return; }
    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) en.target.classList.add('visible'); });
    }, { threshold: 0.1 });
    els.forEach(function (el) { obs.observe(el); });
  }

  /* ---------- Enquiry Modal ---------- */
  window.openEnquiryModal = function () {
    var m = document.getElementById('enquiryModal');
    if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; }
  };
  window.closeEnquiryModal = function () {
    var m = document.getElementById('enquiryModal');
    if (m) { m.classList.remove('active'); document.body.style.overflow = 'auto'; }
  };
  function initModal() {
    var m = document.getElementById('enquiryModal');
    if (m) m.addEventListener('click', function (e) { if (e.target === this) window.closeEnquiryModal(); });
    var form = document.getElementById('enquiryForm');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var g = function (id) { var el = document.getElementById(id); return el ? el.value : ''; };
        var rec = {
          name: g('enquiryName'),
          mobile: g('countryCode') + ' ' + g('enquiryMobile'),
          service: g('consultationType'),
          dob: g('enquiryDOB'),
          tob: (g('birthHour') && g('birthMinute')) ? g('birthHour') + ':' + g('birthMinute') : '',
          message: g('enquiryMessage'),
          source: 'Enquiry Popup'
        };
        window.ARJ_saveEnquiry(rec);
        var wa = getCMS().whatsapp || '919835258203';
        var msg = '*New Consultation Enquiry*%0A%0A' +
          '*Name:* ' + encodeURIComponent(rec.name) + '%0A' +
          '*Mobile:* ' + encodeURIComponent(rec.mobile) + '%0A' +
          (rec.service ? '*Consultation For:* ' + encodeURIComponent(rec.service) + '%0A' : '') +
          (rec.dob ? '*Date of Birth:* ' + encodeURIComponent(rec.dob) + '%0A' : '') +
          (rec.tob ? '*Time of Birth:* ' + encodeURIComponent(rec.tob) + '%0A' : '') +
          (rec.message ? '*Message:* ' + encodeURIComponent(rec.message) + '%0A' : '');
        window.open('https://wa.me/' + wa + '?text=' + msg, '_blank');
        window.closeEnquiryModal();
        form.reset();
        alert('Thank you! Your enquiry has been submitted. We will contact you soon.');
      });
    }
  }

  /* ---------- Booking / Contact forms (consultant.html & contact section) ---------- */
  function initBookingForms() {
    document.querySelectorAll('form[data-booking-form]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var d = Object.fromEntries(new FormData(this).entries());
        window.ARJ_saveEnquiry({
          name: d.name || '', mobile: d.mobile || '', email: d.email || '',
          service: d.service || '', mode: d.mode || '', language: d.language || '',
          appointment: d.appointmentDate || '', dob: d.dob || '', tob: d.tob || '',
          pob: ((d.pobCity || '') + ' ' + (d.pobRegion || '')).trim(),
          address: d.address || '', message: d.message || '', source: this.getAttribute('data-booking-form')
        });
        var wa = getCMS().whatsapp || '919835258203';
        var msg = encodeURIComponent(
          '\n📩 *New Consultation Booking*\n' +
          '🔹 Service: ' + (d.service || '-') + '\n🔹 Mode: ' + (d.mode || '-') + '\n🔹 Language: ' + (d.language || '-') +
          '\n🔹 Appointment: ' + (d.appointmentDate || '-') +
          '\n\n👤 *Client Details*\n• Name: ' + (d.name || '-') + '\n• Mobile: ' + (d.mobile || '-') +
          '\n• Email: ' + (d.email || '-') + '\n• Address: ' + (d.address || '-') +
          '\n\n🌙 *Birth Details*\n• DOB: ' + (d.dob || '-') + '\n• TOB: ' + (d.tob || '-') +
          '\n• Birth Place: ' + ((d.pobCity || '-') + ', ' + (d.pobRegion || '-')) +
          '\n\n📝 Message: ' + (d.message || '-') + '\n— Website Lead');
        window.open('https://wa.me/' + wa + '?text=' + msg, '_blank');
        alert('✅ Your booking details have been sent successfully! We will contact you soon.');
        this.reset();
      });
    });
  }

  /* ---------- Testimonials (Admin-managed) ---------- */
  function esc(s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function renderTestimonials() {
    var grid = document.getElementById('testimonials-grid');
    if (!grid) return;
    var list;
    try { list = JSON.parse(localStorage.getItem('arj_testimonials') || 'null'); } catch (e) { return; }
    if (!list || !list.length) return;
    grid.innerHTML = list.map(function (t) {
      var initials = esc((t.name || 'A').split(' ').map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase());
      var stars = '★'.repeat(parseInt(t.stars || 5, 10));
      return '<div class="testimonial-card rounded-2xl p-6 md:p-8 shadow-lg fade-in visible">' +
        '<div class="flex items-center mb-4">' +
        '<div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-gradient-to-br from-yellow-500 to-yellow-600 flex items-center justify-center text-white font-bold">' + initials + '</div>' +
        '<div class="ml-4"><h4 class="font-bold text-indigo-950">' + esc(t.name) + '</h4>' +
        '<p class="text-sm text-gray-500">' + esc(t.role) + '</p></div></div>' +
        '<div class="text-yellow-500 mb-3">' + stars + '</div>' +
        '<p class="text-gray-600 leading-relaxed italic">"' + esc(t.text) + '"</p></div>';
    }).join('');
  }

  /* ---------- Gallery (Admin-managed) ---------- */
  function renderGallery() {
    var grid = document.getElementById('gallery-grid');
    if (!grid) return;
    var list;
    try { list = JSON.parse(localStorage.getItem('arj_gallery') || 'null'); } catch (e) { return; }
    if (!list || !list.length) return;
    grid.innerHTML = list.map(function (src, i) {
      return '<div class="gallery-item rounded-xl overflow-hidden shadow-lg fade-in visible" onclick="openLightbox(this)">' +
        '<img src="' + esc(src) + '" alt="Gallery Image ' + (i + 1) + '" class="w-full h-48 md:h-64 object-cover"></div>';
    }).join('');
  }

  /* ---------- Lightbox ---------- */
  window.openLightbox = function (el) {
    var img = el.querySelector('img');
    var lb = document.getElementById('lightbox');
    var lbImg = document.getElementById('lightboxImg');
    if (img && lb && lbImg) { lbImg.src = img.src; lb.classList.add('active'); document.body.style.overflow = 'hidden'; }
  };
  window.closeLightbox = function () {
    var lb = document.getElementById('lightbox');
    if (lb) { lb.classList.remove('active'); document.body.style.overflow = 'auto'; }
  };
  function initLightbox() {
    var lb = document.getElementById('lightbox');
    if (lb) lb.addEventListener('click', function (e) { if (e.target === this) window.closeLightbox(); });
  }

  /* ---------- Generic CMS lists (blog / horoscope / faq rendered from admin) ---------- */
  function renderCMSList(storeKey, containerId, tpl) {
    var c = document.getElementById(containerId);
    if (!c) return;
    var list;
    try { list = JSON.parse(localStorage.getItem(storeKey) || 'null'); } catch (e) { return; }
    if (!list || !list.length) return;
    c.innerHTML = list.map(tpl).join('');
  }
  window.ARJ_renderDynamic = function () {
    renderCMSList('arj_blog', 'blog-grid', function (p) {
      return '<article class="bg-white rounded-2xl shadow-lg overflow-hidden fade-in visible">' +
        (p.image ? '<img src="' + esc(p.image) + '" alt="' + esc(p.title) + '" class="w-full h-48 object-cover">' : '') +
        '<div class="p-6"><p class="text-yellow-600 text-sm font-semibold mb-2">' + esc(p.date) + '</p>' +
        '<h3 class="text-xl font-bold text-indigo-950 mb-3">' + esc(p.title) + '</h3>' +
        '<p class="text-gray-600 leading-relaxed">' + esc(p.excerpt) + '</p></div></article>';
    });
    renderCMSList('arj_horoscope', 'horoscope-grid', function (h) {
      return '<div class="bg-white rounded-2xl shadow-lg p-6 fade-in visible">' +
        '<div class="flex items-center gap-3 mb-3"><span class="text-3xl">' + esc(h.icon || '♈') + '</span>' +
        '<h3 class="text-xl font-bold text-indigo-950">' + esc(h.sign) + '</h3></div>' +
        '<p class="text-gray-600 leading-relaxed">' + esc(h.text) + '</p></div>';
    });
    renderCMSList('arj_faq', 'faq-list', function (f) {
      return '<div class="bg-white rounded-2xl shadow-lg p-6 fade-in visible">' +
        '<h3 class="text-lg font-bold text-indigo-950 mb-2">' + esc(f.q) + '</h3>' +
        '<p class="text-gray-600 leading-relaxed">' + esc(f.a) + '</p></div>';
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    applyCMS();
    initNav();
    initFadeIn();
    initModal();
    initBookingForms();
    initLightbox();
    renderTestimonials();
    renderGallery();
    window.ARJ_renderDynamic();
  });
})();
