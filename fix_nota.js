const fs = require('fs');

let content = fs.readFileSync('frontend/src/views/sales/CustomNota.vue', 'utf8');

// 1. Add WA, TikTok, IG to form state
content = content.replace(
    /notes: 'spider, 100% 26\.5'\s*\}\);/g,
    `notes: 'spider, 100% 26.5',
    wa: '0851-3300-5600',
    tiktok: 'pstore_jkt',
    ig: 'pstore_jakarta'
});`
);

// 2. Add social media inputs
content = content.replace(
    /<div class="space-y-4">\s*<div>\s*<label class="block text-xs font-medium text-text-secondary mb-1">Nama CS/,
    `<div class="space-y-4">

                    <!-- Social Media Inputs -->
                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Social Media (Opsional)</label>
                        <div class="space-y-2">
                            <input v-model="form.wa" type="text" placeholder="WhatsApp (contoh: 0851-3300-5600)"
                                class="w-full bg-background-light border border-neutral-200 text-text-primary text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 transition-colors">
                            <input v-model="form.tiktok" type="text" placeholder="TikTok (contoh: pstore_jkt)"
                                class="w-full bg-background-light border border-neutral-200 text-text-primary text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 transition-colors">
                            <input v-model="form.ig" type="text" placeholder="Instagram (contoh: pstore_jakarta)"
                                class="w-full bg-background-light border border-neutral-200 text-text-primary text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block p-2.5 transition-colors">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-secondary mb-1">Nama CS`
);

// 3. Add Social Media display below header
content = content.replace(
    /Pusat Pembelanjaan Online<\/div>\s*<\/div>\s*<\/div>\s*<!-- Divider -->/,
    `Pusat Pembelanjaan Online</div>

                                <!-- Social Bar -->
                                <div class="flex items-center gap-x-3 gap-y-1 mt-2 text-[9px] font-extrabold text-neutral-800 flex-wrap" style="-webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                    <span v-if="form.wa" class="flex items-center gap-1">
                                        <svg class="w-2.5 h-2.5 !text-red-600 fill-current" style="color: #dc2626 !important;" viewBox="0 0 24 24">
                                            <path d="M20 15.5c-1.25 0-2.45-.2-3.57-.57a1.02 1.02 0 00-1.02.24l-2.2 2.2a15.05 15.05 0 01-6.59-6.59l2.2-2.21a.96.96 0 00.25-1.02A11.36 11.36 0 018.5 4c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1 0 9.39 7.61 17 17 17 .55 0 1-.45 1-1v-3.5c0-.55-.45-1-1-1zM12 3v10l3-3h6V3h-9z" />
                                        </svg>
                                        WA: {{ form.wa }}
                                    </span>
                                    
                                    <span v-if="form.wa && (form.tiktok || form.ig)" class="text-neutral-300">|</span>
                                    
                                    <span v-if="form.tiktok" class="flex items-center gap-1">
                                        <svg class="w-2.5 h-2.5 !text-red-600 fill-current" style="color: #dc2626 !important;" viewBox="0 0 24 24">
                                            <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.59-1 .01 2.24.01 4.48 0 6.72-.09 2.93-1.52 5.82-4.32 7.01-2.86 1.29-6.51.83-8.86-1.38-2.43-2.22-2.99-6.09-1.31-8.93 1.49-2.6 4.72-4 7.69-3.43v4.25c-1.82-.35-3.87.19-4.98 1.69-1.13 1.48-1.09 3.72-.02 5.22 1.15 1.66 3.58 2.27 5.44 1.4 1.71-.73 2.71-2.59 2.76-4.44.06-3.34.03-6.68.03-10.02l.02-.31z" />
                                        </svg>
                                        TikTok: {{ form.tiktok }}
                                    </span>

                                    <span v-if="form.tiktok && form.ig" class="text-neutral-300">|</span>
                                    
                                    <span v-if="form.ig" class="flex items-center gap-1">
                                        <svg class="w-2.5 h-2.5 !text-red-600 fill-current" style="color: #dc2626 !important;" viewBox="0 0 24 24">
                                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                                        </svg>
                                        IG: {{ form.ig }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Divider -->`
);

// 4. Force colors on all 4 icons
content = content.replace(
    /class="w-7 h-7 rounded-full border border-neutral-200\/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm text-red-600"/g,
    'class="w-7 h-7 rounded-full border border-neutral-200/60 flex items-center justify-center shrink-0 bg-neutral-50 shadow-sm !text-red-600" style="color: #dc2626 !important; background-color: #fafafa !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;"'
);

// 5. Force colors on Selisih Harga
content = content.replace(
    /<span class="font-black text-red-600 text-\[10px\] uppercase tracking-wider">Selisih Harga<\/span>/,
    '<span class="font-black !text-red-600 text-[10px] uppercase tracking-wider" style="color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">Selisih Harga</span>'
);
content = content.replace(
    /<span class="text-red-600 font-black text-sm">/,
    '<span class="!text-red-600 font-black text-sm" style="color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">'
);

// 6. Force colors on Yang Harus Dibayarkan Trapezoid & Total
content = content.replace(
    /<div class="flex rounded-xl overflow-hidden shadow-md h-\[60px\] bg-red-600 w-full relative">/,
    '<div class="flex rounded-xl overflow-hidden shadow-md h-[60px] bg-red-600 w-full relative" style="background-color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">'
);
content = content.replace(
    /<div class="bg-neutral-950 text-white pl-4 pr-8 flex flex-col justify-center shrink-0 select-none pointer-events-none" style="clip-path: polygon\(0 0, 100% 0, 82% 100%, 0% 100%\); z-index: 2;">/,
    '<div class="bg-neutral-950 !text-white pl-4 pr-8 flex flex-col justify-center shrink-0 select-none pointer-events-none" style="clip-path: polygon(0 0, 100% 0, 82% 100%, 0% 100%); z-index: 2; background-color: #0a0a0a !important; color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">'
);
content = content.replace(
    /<div class="text-\[8px\] font-black uppercase tracking-wider leading-none text-white">Yang Harus<\/div>/,
    '<div class="text-[8px] font-black uppercase tracking-wider leading-none !text-white" style="color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">Yang Harus</div>'
);
content = content.replace(
    /<div class="text-\[8px\] font-black uppercase tracking-wider leading-tight text-white">Dibayarkan<\/div>/,
    '<div class="text-[8px] font-black uppercase tracking-wider leading-tight !text-white" style="color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">Dibayarkan</div>'
);
content = content.replace(
    /<div class="flex-1 flex items-center justify-end pr-4 text-white" style="z-index: 1;">\s*<span class="text-lg sm:text-xl font-black text-white tracking-tight">/,
    `<div class="flex-1 flex items-center justify-end pr-4 !text-white" style="z-index: 1; color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
                                        <span class="text-lg sm:text-xl font-black !text-white tracking-tight" style="color: #ffffff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">`
);

// 7. Inject print CSS rules
let printCss = `
    /* Robustly hide ALL other elements at the root body level to avoid cross-browser rendering bugs and conflicts */
    body> :not(#app) {
        display: none !important;
    }

    html,
    body {
        height: auto !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        overflow: visible !important;
    }

    /* COMPLETE PARENT RESET: Strips away viewport constraints, flex centering, and max-height crushing */
    #receipt-content {
        display: block !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        box-sizing: border-box !important;
        background: white !important;
        z-index: 9999999 !important;
        flex: none !important;
        align-items: flex-start !important;
        justify-content: flex-start !important;
        transform: none !important;
        border: 0 !important;
        outline: 0 !important;
    }

    /* CRITICAL FIX: Direct fitting to physical dimensions with free-flowing bottom overflow to prevent clipping signatures */
    .nota-paper {
        border: none !important;
        box-shadow: none !important;
        padding: 4mm 8mm !important;
        margin: 0 auto !important;
        color: black !important;
        background: white !important;
        border-radius: 0 !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;

        width: 100% !important;
        max-width: 210mm !important;
        height: auto !important;
        min-height: auto !important;
        max-height: none !important;
        overflow: visible !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;

        transform-origin: top center !important;
    }

    /* COMPLETE ARTIFACT ELIMINATION */
    #receipt-content,
    #receipt-content *,
    .nota-paper,
    .nota-paper * {
        box-shadow: none !important;
        text-shadow: none !important;
        filter: none !important;
        -webkit-filter: none !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }`;

content = content.replace(
    /@media print \{\s*@page \{\s*margin: 0;\s*\}/,
    `@media print {
    @page {
        margin: 0;
    }
${printCss}`
);

fs.writeFileSync('frontend/src/views/sales/CustomNota.vue', content, 'utf8');
console.log('Successfully rebuilt CustomNota.vue with all fixes!');
