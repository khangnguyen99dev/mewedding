import{b as p}from"./core-B9Pp5rMg.js";import"./preload-helper-I4rgV-VL.js";const i=window.__INV__??{};function m(){document.getElementById("__inv_guard")?.remove(),document.documentElement.style.visibility=""}function f(){for(const[e,n]of Object.entries(i.text??{})){if(n==null||n==="")continue;const o=document.getElementById(e);if(!o)continue;const t=o.querySelector(".ladi-headline, .ladi-paragraph, .ladi-button-text, .ladi-list, .ladi-button")??o;n.includes(`
`)?t.innerHTML=n.split(`
`).map(a=>a.replace(/[&<>]/g,r=>({"&":"&amp;","<":"&lt;",">":"&gt;"})[r])).join("<br>"):t.textContent=n,i.fit?.includes(e)&&(t.style.whiteSpace="nowrap")}}function u(){for(const e of i.fit??[]){const n=document.getElementById(e);if(!n)continue;const o=n.querySelector(".ladi-headline, .ladi-paragraph")??n;o.style.whiteSpace="nowrap";const t=n.clientWidth,a=o.scrollWidth;if(t>0&&a>t){const r=parseFloat(getComputedStyle(o).fontSize)||16;o.style.fontSize=`${Math.max(8,r*t/a*.97)}px`}}}function g(){const e=Object.entries(i.images??{}).filter(([,o])=>o);if(!e.length)return;const n=o=>{for(const[t,a]of e)if(o.includes(t))return a;return null};for(const o of Array.from(document.styleSheets)){let t;try{t=o.cssRules}catch{continue}if(t)for(const a of Array.from(t)){const r=a.style;if(!r||!r.backgroundImage||r.backgroundImage==="none")continue;const l=n(r.backgroundImage);l&&(r.backgroundImage=`url("${l}")`)}}document.querySelectorAll('[style*="background-image"]').forEach(o=>{const t=n(o.style.backgroundImage);t&&(o.style.backgroundImage=`url("${t}")`)}),document.querySelectorAll("img").forEach(o=>{const t=n(o.src);t&&(o.src=t)})}function v(){if(!i.music)return;const e=document.querySelector("audio");e&&(e.src=i.music,e.setAttribute("data-audio",""))}function s(e){if(!e)return null;const n=document.getElementById(e);return n?n.querySelector(".ladi-container")??n:null}function b(){const e=s(i.sections?.gallery);if(!e||!i.gallery?.length)return;const n=i.gallery.map(o=>`<figure><img loading="lazy" src="${o.thumb}" data-lightbox data-full="${o.full}" alt="${o.alt??""}"></figure>`).join("");e.innerHTML=`<div class="inv-injected">
        <h2 class="inv-injected__title">${i.blocks?.gallery?.heading??"Album Ảnh"}</h2>
        ${i.blocks?.gallery?.tagline?`<p class="inv-injected__sub">${i.blocks.gallery.tagline}</p>`:""}
        <div class="inv-grid">${n}</div>
    </div>`,e.removeAttribute("style"),e.style.height="auto"}function y(){const e=i.blocks?.rsvp,n=s(i.sections?.rsvp);if(!n||!e?.enabled)return;const o=(e.foods??[]).map(t=>`<option value="${t}">${t}</option>`).join("");n.innerHTML=`<div class="inv-injected">
        <h2 class="inv-injected__title">${e.heading??"Xác Nhận Tham Dự"}</h2>
        ${e.intro?`<p class="inv-injected__sub">${e.intro}</p>`:""}
        <p class="inv-counter">Đã có <strong data-rsvp-guests>0</strong> khách xác nhận tham dự</p>
        <form class="inv-pubform" data-rsvp-form>
            <div class="inv-pubform__row">
                <input name="name" placeholder="Họ và tên" required maxlength="120">
                <input name="phone" placeholder="Số điện thoại" maxlength="20">
            </div>
            <div class="inv-pubform__row">
                <select name="attendance"><option value="yes">Tôi sẽ tham dự</option><option value="no">Rất tiếc, tôi không thể</option><option value="maybe">Chưa chắc chắn</option></select>
                <input type="number" name="guest_count" min="1" max="20" value="1" style="max-width:110px">
            </div>
            ${o?`<select name="food_option"><option value="">-- Chọn món --</option>${o}</select>`:""}
            <textarea name="notes" placeholder="Lời nhắn (tuỳ chọn)" maxlength="500"></textarea>
            <button type="submit">Xác nhận tham dự</button>
            <p data-form-msg style="text-align:center;min-height:1.2em"></p>
        </form>
    </div>`,n.removeAttribute("style"),n.style.height="auto"}function x(){const e=i.blocks?.guestbook,n=s(i.sections?.guestbook);!n||!e?.enabled||(n.innerHTML=`<div class="inv-injected">
        <h2 class="inv-injected__title">${e.heading??"Gửi Lời Chúc"}</h2>
        ${e.intro?`<p class="inv-injected__sub">${e.intro}</p>`:""}
        <form class="inv-pubform" data-guestbook-form>
            <div class="inv-pubform__row">
                <input name="name" placeholder="Họ và tên" required maxlength="80">
                <select name="emoji" style="max-width:84px"><option value="❤️">❤️</option><option value="🎉">🎉</option><option value="🥰">🥰</option><option value="🌸">🌸</option></select>
            </div>
            <textarea name="message" placeholder="Lời chúc của bạn..." required maxlength="500"></textarea>
            <button type="submit">Gửi lời chúc</button>
            <p data-form-msg style="text-align:center;min-height:1.2em"></p>
        </form>
        <div class="inv-wishes" data-wishes style="max-width:520px;margin:24px auto 0;display:grid;gap:12px;max-height:360px;overflow:auto"></div>
    </div>`,n.removeAttribute("style"),n.style.height="auto")}function _(){const e=i.sections??{},n=[[/(xác nhận|tham dự|đặt ngay|phản hồi|r\.?s\.?v\.?p)/i,e.rsvp],[/(lời chúc|chúc mừng|sổ lưu|gửi lời)/i,e.guestbook],[/(album|ảnh cưới|hình ảnh|xem thêm hình)/i,e.gallery]],o=t=>{const a=t?document.getElementById(t):null;return a?(a.scrollIntoView({behavior:"smooth",block:"start"}),!0):!1};document.addEventListener("click",t=>{const a=t.target;if(a?.closest?.(".inv-injected, .inv-pubform, [data-wishes], .inv-drawer, .inv-fab"))return;const r=a?.closest?.('[data-action="true"], .ladi-button, a.ladi-element, .ladi-headline');if(!r)return;const l=(r.textContent??"").trim().toLowerCase();if(l){if(/(bản đồ|chỉ đường|xem map)/i.test(l)){i.mapUrl&&(t.preventDefault(),t.stopPropagation(),window.open(i.mapUrl,"_blank","noopener"));return}for(const[h,c]of n)if(c&&h.test(l)&&o(c)){t.preventDefault(),t.stopPropagation();return}}},!0)}function w(){(i.hide??[]).forEach(t=>{const a=document.getElementById(t);a&&a.style.setProperty("display","none","important")});const e=i.nav??[],n=document.createElement("div");if(n.className="inv-fab",n.innerHTML='<button class="inv-fab__btn" data-inv-top aria-label="Lên đầu trang">↑</button>'+(e.length?'<button class="inv-fab__btn" data-inv-menu aria-label="Menu">☰</button>':""),document.body.appendChild(n),n.querySelector("[data-inv-top]").addEventListener("click",()=>window.scrollTo({top:0,behavior:"smooth"})),!e.length)return;const o=document.createElement("nav");o.className="inv-drawer",o.innerHTML=e.map(t=>`<a href="#" data-sec="${t.section}">${t.label}</a>`).join(""),document.body.appendChild(o),n.querySelector("[data-inv-menu]").addEventListener("click",t=>{t.stopPropagation(),o.classList.toggle("open")}),o.querySelectorAll("a").forEach(t=>t.addEventListener("click",a=>{a.preventDefault(),document.getElementById(t.dataset.sec)?.scrollIntoView({behavior:"smooth",block:"start"}),o.classList.remove("open")})),document.addEventListener("click",t=>{!o.contains(t.target)&&!n.contains(t.target)&&o.classList.remove("open")})}function d(){try{f(),g(),v(),b(),y(),x()}catch(e){console.error("hydration error",e)}m(),p(),_(),w(),u(),document.fonts?.ready?.then(u).catch(()=>{})}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",d):d();setTimeout(m,2500);
