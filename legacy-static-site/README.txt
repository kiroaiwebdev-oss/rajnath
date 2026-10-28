===============================================================
 ACHARYA DR. RAJNATH JHA — WEBSITE v2 (with Admin Panel)
===============================================================

📁 FILES
- index.html ............ Homepage (design bilkul original jaisa)
- about.html ............ Full biography, qualifications, awards
- consultant.html ....... Booking page
- contact.html .......... Contact + map + form
- testimonial.html ...... Client reviews
- blog.html ............. Blog (admin se manageable)
- faq.html .............. FAQ (admin se manageable)
- weekly-horoscope.html . Rashifal (admin se manageable)
- free-kundli-generator.html ... Free kundli tool
- 10 service/remedy pages (kundali, business, love, numerology,
  palmistry, vastu, gemstone, pyramid, puja, mantra)
- privacy.html .......... Privacy policy
- admin.html ............ 🔐 ADMIN PANEL
- assets/css, assets/js, assets/img

🔐 ADMIN PANEL
- URL: admin.html
- Default login:  username = admin | password = rajnath123
- Zaroori: Login ke baad Settings mein jaakar password BADAL LEIN.

Admin panel se aap manage kar sakte hain:
  📊 Dashboard — enquiries ka overview
  📩 Enquiries — website ke saare form submissions (status change,
     WhatsApp reply, CSV export)
  📝 Site Content — phone, WhatsApp, email, address, timings,
     hero heading, stats, footer text
  💬 Testimonials — add/edit/delete reviews
  🖼️ Gallery — images add/remove
  📰 Blog — posts publish/edit/delete
  ♈ Weekly Horoscope — 12 rashis ke predictions update
  ❓ FAQ — sawal-jawab manage
  ⚙️ Settings — password change, full backup export/import, reset

⚠️ IMPORTANT NOTE (jarur padhein)
- Yeh site bina server/database ke chalti hai (pure HTML/CSS/JS).
  Admin panel ka data browser ke localStorage mein save hota hai —
  matlab changes usi browser/computer par dikhte hain jahan se
  edit kiya gaya. Live hosting par bhi admin se kiye changes sirf
  usi device ke browser par reflect honge.
- Agar aap chahte hain ki admin se kiya change SABHI visitors ko
  dikhe (real database), to batayein — next step mein backend
  (Firebase/Supabase) add kar sakte hain.

🌐 HOSTING
- Saari files ko apne hosting (cPanel/Hostinger/Netlify/Vercel)
  ke public folder mein upload karein — bas. Koi build step nahi.
- Domain ke saath https zaroor enable karein.

📞 FORMS
- Har enquiry/booking form submit par:
  1) WhatsApp par formatted message khulta hai (919835258203)
  2) Entry admin panel ke Enquiries section mein save hoti hai
===============================================================
