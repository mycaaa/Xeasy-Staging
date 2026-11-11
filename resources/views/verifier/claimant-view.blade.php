@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="{{ asset('vendor/cdn/remixicon@4.3.0/fonts/remixicon.css') }}" rel="stylesheet">
<style>
/* ════════════════════════════════════════════
   MY CLAIMS — PREMIUM REDESIGN
   ════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; }
.mcv-root, .mcv-root *:not([class*="ri-"]):not(i) { font-family: 'Inter', sans-serif !important; }
.mcv-root i, .mcv-root [class*="ri-"] { font-family: 'remixicon' !important; font-style: normal; }

.mcv-root { width: 100%; padding: 0; }

/* ── Top Bar ── */
.mcv-topbar {
    display: flex; justify-content: space-between; align-items: center;
    gap: 12px; margin-bottom: 18px; flex-wrap: wrap;
}
.mcv-topbar-title {
    font-size: 1.1rem; font-weight: 800; color: #1e293b; margin: 0;
    display: flex; align-items: center; gap: 9px; letter-spacing: -.3px;
}
.mcv-topbar-title i { font-size: 1.2rem; color: #15648C; }
.mcv-topbar-sub { font-size: .74rem; color: #94a3b8; margin-top: 2px; }
.mcv-topbar-actions { display: flex; gap: 8px; flex-shrink: 0; }
.mcv-btn-h {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 14px; border-radius: 8px; border: none;
    font-size: .76rem; font-weight: 700; cursor: pointer;
    transition: all .18s ease; text-decoration: none; white-space: nowrap;
}
.mcv-btn-new { background: #15648C; color: #fff; box-shadow: 0 2px 8px rgba(21,100,140,.25); }
.mcv-btn-new:hover { background: #0d4a6a; color: #fff; transform: translateY(-1px); }
.mcv-btn-ref { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
.mcv-btn-ref:hover { background: #e2e8f0; color: #15648C; }

/* ── Section (white card wrapper) ── */
.mcv-section {
    margin-bottom: 20px;
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e8edf3;
    box-shadow: 0 2px 10px rgba(0,0,0,.05);
    overflow: hidden;
}
.mcv-section-hdr {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    padding: 14px 18px 12px;
    border-bottom: 1px solid #f1f5f9;
    background: #fafbfd;
}
.mcv-section-title {
    display: flex; align-items: center; gap: 7px;
    font-size: .92rem; font-weight: 800; color: #1e293b;
}
.mcv-section-title i { font-size: 1rem; color: #15648C; }
.mcv-section-count {
    background: #eff6ff; color: #15648C; border-radius: 50px;
    padding: 1px 9px; font-size: .72rem; font-weight: 800;
}
.mcv-month-filter {
    margin-left: auto;
    border: 1px solid #e2e8f0; border-radius: 8px; padding: 5px 10px;
    font-size: .76rem; font-weight: 600; color: #475569; background: #fff;
    cursor: pointer;
}
@keyframes mcvFadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }

/* ── Progress Stage Strip (closed month) ── */
.mcv-stage-strip {
    display: flex; align-items: flex-start;
    padding: 14px 18px 10px; background: #fff;
    border-bottom: 1px solid #e8edf3; overflow-x: auto;
}
.mcv-stage { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; min-width: 58px; position: relative; }
.mcv-stage:not(:last-child)::after {
    content: ''; position: absolute; top: 13px; left: 55%; right: -45%;
    height: 2px; background: #e2e8f0; z-index: 0;
}
.mcv-stage.done:not(:last-child)::after { background: #10b981; }
.mcv-stage-dot {
    width: 26px; height: 26px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: #f1f5f9; border: 2px solid #e2e8f0; color: #94a3b8;
    font-size: .78rem; z-index: 1; flex-shrink: 0;
}
.mcv-stage.done .mcv-stage-dot { background: #10b981; border-color: #10b981; color: #fff; }
.mcv-stage-label { font-size: .62rem; font-weight: 700; color: #94a3b8; text-align: center; }
.mcv-stage.done .mcv-stage-label { color: #1e293b; }
.mcv-stage-date { font-size: .58rem; color: #94a3b8; }

/* ── States ── */
.mcv-loading {
    text-align: center; padding: 48px 24px;
    display: flex; flex-direction: column; align-items: center; gap: 10px; color: #94a3b8;
    font-size: .82rem; font-weight: 500;
}
.mcv-empty {
    text-align: center; padding: 50px 24px;
    background: #fff; border-radius: 14px;
    border: 2px dashed #e2e8f0;
    width: 100%; flex-shrink: 0;
}
.mcv-empty-icon {
    width: 60px; height: 60px;
    background: #f1f5f9; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 1.6rem; color: #94a3b8;
}
.mcv-empty p { color: #64748b; font-size: .83rem; font-weight: 500; margin: 0; }

/* ════════════════════════════════════════
   SCROLL CONTAINERS & CARD PADDING
   ════════════════════════════════════════ */
/* Horizontal — for drafts & closed months */
.mcv-hscroll {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 16px 20px 20px;
    scroll-snap-type: x proximity;
    align-items: stretch;
}
.mcv-hscroll::-webkit-scrollbar { height: 6px; }
.mcv-hscroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
.mcv-hscroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.mcv-hscroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

/* Vertical — for open months (stacked full-width) */
.mcv-vlist { display: flex; flex-direction: column; padding: 16px 20px; gap: 14px; }
.mcv-loading-inner { padding: 16px 20px; }

/* ════════════════════════════════════════
   MONTH ACCORDION TILES (Full-width vertical stack)
   ════════════════════════════════════════ */
.month-tile, .closed-month-tile {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 1px 6px rgba(0,0,0,.04);
    transition: box-shadow .2s ease;
    width: 100%;
    display: flex;
    flex-direction: column;
    margin: 0;
}
.month-tile:hover, .closed-month-tile:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }

.month-tile-header {
    padding: 14px 18px 14px 22px;
    cursor: pointer; position: relative; user-select: none;
    display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
    transition: background .15s;
}
.month-tile-header:hover { background: #fafbfd; }

.mtr-left { display: flex; align-items: center; gap: 14px; flex: 1; min-width: 280px; flex-wrap: wrap; }
.mtr-badge-wrap { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

.mcv-accent {
    position: absolute; top: 0; left: 0; bottom: 0; width: 5px;
}
.mcv-accent-open   { background: linear-gradient(180deg, #10b981, #059669); }
.mcv-accent-closed { background: linear-gradient(180deg, #7c3aed, #6d28d9); }
.mcv-accent-draft  { background: linear-gradient(180deg, #f59e0b, #d97706); }

.mcv-month-badge {
    color: #fff; border-radius: 10px;
    padding: 7px 13px; font-size: .95rem; font-weight: 800;
    letter-spacing: -.4px; flex-shrink: 0;
    min-width: 52px; text-align: center; line-height: 1.2;
}
.mcv-badge-open   { background: linear-gradient(135deg,#10b981,#059669); box-shadow: 0 2px 8px rgba(16,185,129,.3); }
.mcv-badge-closed { background: linear-gradient(135deg,#7c3aed,#6d28d9); box-shadow: 0 2px 8px rgba(124,58,237,.3); }

.mcv-month-body { flex: 1; min-width: 0; }
.mcv-month-row1 { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.mcv-mi { font-size: .82rem; color: #475569; }
.mcv-mi strong { color: #1e293b; font-weight: 700; }
.mcv-month-row2 { display: flex; align-items: center; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
.mcv-sub {
    font-size: .74rem;
    color: #475569;
    display: inline-flex !important;
    align-items: center;
    gap: 5px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 3px 8px;
    border-radius: 6px;
    line-height: 1.3;
    opacity: 1 !important;
    width: auto !important;
    min-height: unset !important;
}
.mcv-sub strong { font-weight: 700; color: #1e293b; }
.mcv-sub-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.mcv-sub-vr { background: #f0fdf4 !important; border-color: #bbf7d0 !important; color: #166534 !important; }
.mcv-sub-vr strong { color: #15803d !important; }
.mcv-sub-vc { background: #eff6ff !important; border-color: #bfdbfe !important; color: #1e40af !important; }
.mcv-sub-vc strong { color: #2563eb !important; }
.mcv-sub-dc { background: #fef2f2 !important; border-color: #fecaca !important; color: #991b1b !important; }
.mcv-sub-dc strong { color: #dc2626 !important; }

.mcv-month-right { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 2px; }

/* Action buttons */
.mcv-ab {
    display: inline-flex; align-items: center; gap: 4px;
    border-radius: 8px; padding: 5px 11px;
    font-size: .73rem; font-weight: 700;
    cursor: pointer; transition: all .17s ease;
    text-decoration: none; white-space: nowrap; border: 1px solid transparent;
}
.mcv-ab-detail { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.mcv-ab-detail:hover { background: #2563eb; color: #fff; }
.mcv-ab-print  { background: #f8fafc; color: #64748b; border-color: #e2e8f0; }
.mcv-ab-print:hover  { background: #64748b; color: #fff; }
.mcv-ab-km     { background: #f0fdf4; color: #059669; border-color: #a7f3d0; }
.mcv-ab-km:hover     { background: #059669; color: #fff; }
.mcv-ab-courier{ background: #fefce8; color: #ca8a04; border-color: #fde68a; }
.mcv-ab-courier:hover{ background: #ca8a04; color: #fff; }
.mcv-ab-courier.posted{ background: #f0fdf4; color: #059669; border-color: #a7f3d0; }
.mcv-ab-submit { background: #10b981; color: #fff; border-color: #059669; box-shadow: 0 2px 6px rgba(16,185,129,.25); }
.mcv-ab-submit:hover { background: #059669; color: #fff; transform: translateY(-1px); }

/* Status chips */
.mcv-chip {
    display: inline-flex; align-items: center;
    border-radius: 20px; padding: 3px 10px;
    font-size: .69rem; font-weight: 700; border: 1px solid transparent;
}
.mcv-chip-open { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
.mcv-chip-closed { background: #f5f3ff; color: #7c3aed; border-color: #ddd6fe; }
.mcv-chip-sub { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }

/* Chevron */
.mcv-chevron {
    width: 28px; height: 28px;
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 7px; display: flex; align-items: center; justify-content: center;
    transition: all .25s ease; font-size: 1rem; color: #94a3b8; flex-shrink: 0;
}
.month-tile-header[aria-expanded="true"] .mcv-chevron {
    transform: rotate(180deg);
    background: #eff6ff; color: #2563eb; border-color: #bfdbfe;
}

/* ═══════════════════════════════
   CLAIM CARDS (inside tile)
   ═══════════════════════════════ */
.mcv-claims-body {
    background: #f8fafc;
    border-top: 1px solid #e8edf3;
    padding: 16px 18px;
}
.claim-cards-row { display: flex; gap: 14px; overflow-x: auto; padding: 4px 2px 10px; }
.claim-cards-row::-webkit-scrollbar { height: 5px; }
.claim-cards-row::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

/* Dark Bold EXP ID badge */
.mcv-exp-id {
    font-weight: 800 !important;
    color: #0f172a !important; /* Bold dark slate */
    font-size: .76rem;
    letter-spacing: -.2px;
    background: #f1f5f9;
    padding: 2px 7px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    display: inline-flex;
    align-items: center;
    gap: 2px;
    white-space: nowrap;
}

/* Card details (claimed, verified, approved) */
.cc-amt-detail {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 4px;
    padding-top: 4px;
    border-top: 1px dashed #e2e8f0;
}
.cc-amt-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .69rem;
    color: #64748b;
    gap: 4px;
}
.cc-amt-row strong {
    font-weight: 700;
}
.cc-amt-date {
    font-size: .62rem;
    color: #94a3b8;
    font-weight: 500;
}

/* Card remarks */
.cc-remark, .draft-remark {
    font-size: .68rem;
    color: #78350f;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 5px;
    padding: 3px 6px;
    margin-top: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.25;
}
.cc-remark-verifier {
    font-size: .68rem;
    color: #1e40af;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 5px;
    padding: 3px 6px;
    margin-top: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.25;
}
.cc-remark-approver {
    font-size: .68rem;
    color: #6b21a8;
    background: #faf5ff;
    border: 1px solid #e9d5ff;
    border-radius: 5px;
    padding: 3px 6px;
    margin-top: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.25;
}
.cc-remark-denied {
    font-size: .68rem;
    color: #991b1b;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 5px;
    padding: 3px 6px;
    margin-top: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.25;
}

/* Claim card — image top 2/3, info bottom 1/3 */
.claim-card-item {
    width: 180px;
    flex-shrink: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 5px rgba(0,0,0,.05);
    transition: all .2s ease;
    display: flex;
    flex-direction: column;
    margin: 1px;
}
.claim-card-item:hover { border-color: #93c5fd; box-shadow: 0 5px 18px rgba(37,99,235,.15); transform: translateY(-2px); }

/* Image banner — top 2/3 */
.cc-img-wrap {
    width: 100%; height: 108px;
    background: #f1f5f9;
    display: flex; align-items: center; justify-content: center;
    position: relative; overflow: hidden; flex-shrink: 0;
    cursor: pointer;
}
.cc-img-wrap img {
    width: 100%; height: 100%; object-fit: cover;
    display: block; transition: transform .25s ease;
}
.cc-img-wrap:hover img { transform: scale(1.06); }
.cc-img-placeholder {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    color: #cbd5e1;
}
.cc-img-placeholder i { font-size: 1.8rem; }
.cc-img-placeholder span { font-size: .64rem; font-weight: 600; color: #94a3b8; }
.cc-badge-overlay {
    position: absolute; top: 6px; right: 6px;
}

/* Info bottom */
.cc-info {
    padding: 10px 12px 0;
    display: flex; flex-direction: column; gap: 2px;
    flex: 1;
}
.claim-card-type { font-weight: 700; font-size: .8rem; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.claim-date { font-size: .7rem; color: #94a3b8; }
.claim-amount { font-size: .92rem; font-weight: 800; color: #2563eb; margin-top: 3px; }
.claim-card-footer { padding: 8px 12px 10px; display: flex; justify-content: center; border-top: 1px solid #f1f5f9; margin-top: 8px; }
.claim-view-link {
    font-size: .73rem; font-weight: 700; color: #15648C;
    background: none; border: none; padding: 0; cursor: pointer;
    display: inline-flex; align-items: center; gap: 4px; text-decoration: none;
}
.claim-view-link:hover { color: #0d4a6a; text-decoration: underline; }

/* Status badges */
.sbg { display: inline-block; border-radius: 5px; padding: 2px 8px; font-size: .66rem; font-weight: 700; white-space: nowrap; }
.sbg-verified { background: #dcfce7; color: #16a34a; }
.sbg-denied   { background: #fee2e2; color: #dc2626; }
.sbg-pending  { background: #fef9c3; color: #ca8a04; }
.sbg-financed { background: #ede9fe; color: #7c3aed; }
.sbg-approved { background: #dcfce7; color: #15803d; }
.sbg-draft    { background: #e0f2fe; color: #0284c7; }

/* ═══════════════════════════
   DRAFT BILL CARD — image top 2/3
   ═══════════════════════════ */
.draft-bill-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #fde68a;
    box-shadow: 0 2px 10px rgba(245,158,11,.08);
    transition: all .2s ease;
    flex: 0 0 215px;
    scroll-snap-align: start;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    margin: 1px;
}
.draft-bill-card:hover { box-shadow: 0 6px 24px rgba(245,158,11,.22); transform: translateY(-3px); border-color: #f59e0b; }

/* Image at top — 2/3 of card */
.draft-img-wrap {
    width: 100%; height: 130px;
    background: #f1f5f9;
    position: relative; overflow: hidden; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
}
.draft-img-wrap img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform .25s ease;
}
.draft-img-wrap:hover img { transform: scale(1.06); }
.draft-img-placeholder {
    display: flex; flex-direction: column; align-items: center; gap: 6px; color: #cbd5e1;
}
.draft-img-placeholder i { font-size: 2.2rem; }
.draft-img-placeholder span { font-size: .66rem; font-weight: 600; color: #94a3b8; }

/* Amber accent bar below image */
.draft-accent-bar { height: 3px; background: linear-gradient(90deg, #f59e0b, #fbbf24); flex-shrink: 0; }

/* Extra thumbnails strip (img2, img3) */
.draft-extra-thumbs { display: flex; gap: 5px; padding: 6px 12px 0; }
.draft-extra-thumb {
    width: 40px; height: 32px; object-fit: cover;
    border-radius: 6px; border: 1px solid #e2e8f0;
    cursor: pointer; transition: all .17s ease; flex-shrink: 0;
}
.draft-extra-thumb:hover { border-color: #15648C; transform: scale(1.12); }

/* Info section below image */
.draft-info { padding: 10px 14px 4px; display: flex; flex-direction: column; gap: 3px; flex: 1; }
.dbh-top { display: flex; justify-content: space-between; align-items: center; gap: 6px; }
.draft-type { font-size: .86rem; font-weight: 800; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.draft-meta { font-size: .69rem; color: #94a3b8; }
.draft-amount { font-size: .96rem; font-weight: 800; color: #2563eb; white-space: nowrap; }
.draft-bill-actions {
    display: flex; gap: 8px; justify-content: center;
    margin-top: auto; padding: 10px 12px 12px;
    border-top: 1px solid #fef3c7;
}
.dab {
    display: inline-flex; align-items: center; gap: 5px;
    border: none; border-radius: 8px; padding: 7px 14px;
    font-size: .76rem; font-weight: 700; cursor: pointer;
    transition: all .17s ease; text-decoration: none;
}
.dab-view { background: #dbeafe; color: #1d4ed8; }
.dab-view:hover { background: #1d4ed8; color: #fff; }
.dab-del  { background: #fee2e2; color: #dc2626; }
.dab-del:hover  { background: #dc2626; color: #fff; }

/* ════════════════════════
   MODALS — PREMIUM & COMPACT IMAGES
   ════════════════════════ */
.modal-content { border-radius: 16px !important; border: none !important; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
.mcv-mhdr {
    background: linear-gradient(135deg, #1a3a5c 0%, #15648C 100%);
    padding: 16px 20px;
    display: flex; align-items: center; gap: 12px;
}
.mcv-mhdr-icon {
    width: 34px; height: 34px;
    background: rgba(255,255,255,.18);
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1rem; flex-shrink: 0;
}
.mcv-mhdr h5 { color: #fff; font-size: .92rem; font-weight: 700; margin: 0; flex: 1; }
.mcv-mhdr .btn-close { filter: invert(1) brightness(2); margin: 0; opacity: .8; }

/* Amount strip */
.mcv-amt-strip {
    display: flex; border: 1px solid #e2e8f0;
    border-radius: 12px; overflow: hidden; margin-bottom: 16px;
    background: #f8fafc;
}
.mcv-amt-cell {
    flex: 1; text-align: center; padding: 10px 6px;
    border-right: 1px solid #e2e8f0;
}
.mcv-amt-cell:last-child { border-right: none; }
.mcv-amt-cell .lbl { font-size: .62rem; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; font-weight: 700; }
.mcv-amt-cell .val { font-size: .86rem; font-weight: 800; margin-top: 3px; }
.val-cl { color: #2563eb; }
.val-vf { color: #d97706; }
.val-ap { color: #16a34a; }
.val-fn { color: #7c3aed; }

/* Detail table */
.mcv-tbl { font-size: .8rem; }
.mcv-tbl thead th { background: #1e293b; color: #fff; font-weight: 600; border: none; padding: 10px 11px; font-size: .74rem; white-space: nowrap; }
.mcv-tbl tbody tr:hover { background: #f8fafc; }
.mcv-tbl tbody td { padding: 9px 11px; vertical-align: middle; border-color: #f1f5f9; }

/* Compact table thumbnails in Day Detail modal */
.mcv-tbl-thumb-wrap {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}
.mcv-tbl-thumb {
    width: 38px;
    height: 34px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    transition: all .15s ease;
    display: inline-block;
}
.mcv-tbl-thumb:hover {
    transform: scale(1.14);
    border-color: #15648C;
    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
}

/* Strict sizing for all images inside modal tables */
.mcv-tbl img, .mcv-tbl .draft-thumb, .mcv-tbl .zoom-img {
    width: 42px !important;
    height: 36px !important;
    max-width: 42px !important;
    max-height: 36px !important;
    object-fit: cover !important;
    border-radius: 6px !important;
    border: 1px solid #cbd5e1 !important;
    cursor: pointer !important;
    display: inline-block !important;
    transition: transform .15s ease !important;
    vertical-align: middle !important;
}
.mcv-tbl img:hover {
    transform: scale(1.18) !important;
    border-color: #15648C !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
}

/* Attached docs in draft modal */
.dm-file-chip {
    width: 68px;
    height: 52px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .15s ease;
}
.dm-file-chip:hover {
    transform: scale(1.08);
    border-color: #15648C;
    box-shadow: 0 2px 8px rgba(0,0,0,0.14);
}
.dm-file-chip img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* PDF Card Preview (for top 2/3 of cards when PDF) */
.pdf-card-preview {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    width: 100% !important;
    height: 100% !important;
    background: linear-gradient(145deg, #fff1f2 0%, #ffe4e6 100%) !important;
    color: #e11d48 !important;
    text-decoration: none !important;
    padding: 12px !important;
    box-sizing: border-box !important;
    transition: all 0.2s ease !important;
    cursor: pointer !important;
}
.pdf-card-preview:hover {
    background: linear-gradient(145deg, #ffe4e6 0%, #fecdd3 100%) !important;
    color: #be123c !important;
}
.pdf-card-preview .pdf-icon-badge {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 10px rgba(225, 29, 72, 0.16);
}
.pdf-card-preview .pdf-icon-badge i {
    font-size: 26px;
    color: #e11d48;
}
.pdf-card-preview .pdf-label {
    font-size: 0.74rem;
    font-weight: 700;
    color: #9f1239;
    text-align: center;
}

/* PDF Chips for extra thumbs & table cells */
.mcv-pdf-chip {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 4px !important;
    padding: 4px 8px !important;
    border-radius: 6px !important;
    background: #fff1f2 !important;
    color: #e11d48 !important;
    font-size: 0.72rem !important;
    font-weight: 700 !important;
    text-decoration: none !important;
    border: 1px solid #fecdd3 !important;
    transition: all 0.15s ease !important;
    white-space: nowrap !important;
}
.mcv-pdf-chip:hover {
    background: #ffe4e6 !important;
    color: #be123c !important;
    border-color: #fda4af !important;
    transform: translateY(-1px) !important;
}
.mcv-pdf-thumb {
    width: 38px !important;
    height: 34px !important;
    background: #fff1f2 !important;
    border: 1px solid #fecdd3 !important;
    border-radius: 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #e11d48 !important;
    font-size: 1.1rem !important;
    text-decoration: none !important;
    transition: all 0.15s ease !important;
}
.mcv-pdf-thumb:hover {
    background: #ffe4e6 !important;
    color: #be123c !important;
    transform: scale(1.15) !important;
}

/* Courier form groups */
.mcv-fg { background: #f8fafc; border-radius: 10px; padding: 12px 14px; margin-bottom: 12px; border: 1px solid #e2e8f0; }
.mcv-fg label { font-size: .77rem; font-weight: 700; color: #15648C; margin-bottom: 6px; display: block; }
.mcv-fg .form-control { border-radius: 8px; border-color: #e2e8f0; font-size: .82rem; }
.mcv-fg .form-control:focus { border-color: #15648C; box-shadow: 0 0 0 3px rgba(21,100,140,.1); }

/* ════════════════════════════════════════
   FULL-WINDOW LIGHTBOX (Screen-wide overlay)
   ════════════════════════════════════════ */
.mcv-lightbox {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(6px);
    z-index: 100050;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
}
.mcv-lightbox.active {
    display: flex !important;
}
.mcv-lightbox-box {
    position: relative;
    max-width: 92vw;
    max-height: 90vh;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: mcvZoomIn .2s ease;
}
@keyframes mcvZoomIn {
    from { opacity: 0; transform: scale(.92); }
    to { opacity: 1; transform: scale(1); }
}
.mcv-lightbox-img {
    max-width: 90vw;
    max-height: 86vh;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 16px 50px rgba(0,0,0,0.65);
    background: #fff;
}
.mcv-lightbox-close {
    position: absolute;
    top: -46px;
    right: -6px;
    background: rgba(255,255,255,0.22);
    color: #fff;
    border: none;
    font-size: 1.8rem;
    line-height: 1;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .2s, transform .2s;
}
.mcv-lightbox-close:hover {
    background: rgba(255,255,255,0.45);
    transform: scale(1.08);
}

@keyframes spin { to { transform: rotate(360deg); } }
.spin-me { animation: spin .8s linear infinite; display: inline-block; }
</style>
@endpush

{{-- ══════════════════════════════════════════════════════════
     HTML
     ══════════════════════════════════════════════════════════ --}}
<div class="page-content">
<div class="container-fluid">
<div class="mcv-root">

    {{-- Top Marquee Banner (matches Flutter HomePage marquee notification) --}}
    <div class="mcv-marquee-wrap" style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:8px 14px;margin-bottom:14px;display:flex;align-items:center;gap:10px;font-size:.78rem;color:#92400e;font-weight:600;">
        <span style="display:inline-flex;align-items:center;gap:4px;background:#fef3c7;padding:2px 8px;border-radius:6px;color:#b45309;font-weight:700;flex-shrink:0;">
            <i class="ri-notification-3-line"></i> Notice
        </span>
        <marquee behavior="scroll" direction="left" scrollamount="4" style="flex:1;">
            Please submit all claims before the cutoff date. Ensure odometer reading photos and receipts are attached clearly.
        </marquee>
    </div>

    {{-- Top Bar with Quick Actions --}}
    <div class="mcv-topbar">
        <div>
            <h4 class="mcv-topbar-title">
                <i class="ri-file-list-3-line"></i>
                My Claims
            </h4>
            <div class="mcv-topbar-sub">
                FY: {{ $fYear ?? date('Y').'-'.(date('Y')+1) }}
                &nbsp;·&nbsp; Tap a month to expand details
            </div>
        </div>
        <div class="mcv-topbar-actions">
            <a href="{{ url('/new-claim') }}" class="mcv-btn-h" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;" title="DA Claim">
                <i class="ri-restaurant-line"></i> DA Claim
            </a>
            <a href="{{ url('/odometer') }}" class="mcv-btn-h" style="background:#fef3c7;color:#b45309;border:1px solid #fde68a;" title="Odometer Claim">
                <i class="ri-motorbike-line"></i> Odometer
            </a>
            <a href="{{ url('/new-claim') }}" class="mcv-btn-h mcv-btn-new" title="Upload Bill">
                <i class="ri-upload-cloud-2-line"></i> Upload Bill
            </a>
            <button class="mcv-btn-h mcv-btn-ref" id="mcvRefreshBtn" title="Refresh">
                <i class="ri-refresh-line"></i>
            </button>
        </div>
    </div>

    {{-- Drafts — horizontal scroll cards --}}
    <div class="mcv-section">
        <div class="mcv-section-hdr">
            <span class="mcv-section-title"><i class="ri-draft-line"></i> Drafts</span>
            <span class="mcv-section-count" id="cntDraft">{{ $draftCount ?? 0 }}</span>
        </div>
        <div id="draftsLoading" class="mcv-loading mcv-loading-inner">
            <div class="spinner-border text-primary" style="width:1.6rem;height:1.6rem;border-width:2.5px;"></div>
            <span>Loading drafts…</span>
        </div>
        <div id="draftsList" class="mcv-hscroll"></div>
    </div>

    {{-- Open Months — vertical list, claims inside are horizontal --}}
    <div class="mcv-section">
        <div class="mcv-section-hdr">
            <span class="mcv-section-title"><i class="ri-calendar-check-line"></i> Open Months</span>
            <span class="mcv-section-count" id="cntOpen">{{ $openedCount ?? 0 }}</span>
        </div>
        <div id="openLoading" class="mcv-loading mcv-loading-inner">
            <div class="spinner-border text-primary" style="width:1.6rem;height:1.6rem;border-width:2.5px;"></div>
            <span>Loading open months…</span>
        </div>
        {{-- vertical list: each month tile stacks full-width --}}
        <div id="openList" class="mcv-vlist"></div>
    </div>

    {{-- Closed Months — horizontal scroll cards --}}
    <div class="mcv-section">
        <div class="mcv-section-hdr">
            <span class="mcv-section-title"><i class="ri-archive-2-line"></i> Closed Months</span>
            <span class="mcv-section-count" id="cntClosed">{{ $closedCount ?? 0 }}</span>
            <select id="closedMonthFilter" class="mcv-month-filter">
                <option value="">All Months</option>
            </select>
        </div>
        <div id="closedLoading" class="mcv-loading mcv-loading-inner">
            <div class="spinner-border text-primary" style="width:1.6rem;height:1.6rem;border-width:2.5px;"></div>
            <span>Loading closed months…</span>
        </div>
        <div id="closedList" class="mcv-vlist"></div>
    </div>

</div>{{-- mcv-root --}}
</div>{{-- container-fluid --}}
</div>{{-- page-content --}}

{{-- ══════════════════════════════
     MODALS
     ══════════════════════════════ --}}

{{-- Day Detail --}}
<div class="modal fade" id="dayDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="mcv-mhdr">
                <div class="mcv-mhdr-icon"><i class="ri-calendar-todo-line"></i></div>
                <h5 id="ddModalTitle">Claims Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div id="ddAmtStrip" class="mcv-amt-strip" style="display:none;"></div>
                <div id="ddBody">
                    <div class="mcv-loading"><div class="spinner-border spinner-border-sm text-primary"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Draft Detail --}}
<div class="modal fade" id="draftModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="mcv-mhdr">
                <div class="mcv-mhdr-icon"><i class="ri-file-text-line"></i></div>
                <h5>Draft Bill Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-4">
                        <div class="text-center p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                            <div style="font-size:.62rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;">Exp ID</div>
                            <div class="mt-1"><span class="mcv-exp-id fs-6" id="dm-expid">#—</span></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                            <div style="font-size:.62rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;">Type</div>
                            <div class="fw-bold mt-1" id="dm-type" style="font-size:.8rem;color:#1e293b;"></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                            <div style="font-size:.62rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;">Amount</div>
                            <div class="fw-bold text-primary mt-1" id="dm-amount">—</div>
                        </div>
                    </div>
                </div>
                <div class="mb-3 p-3 rounded-3" style="background:#fffbeb;border:1px solid #fde68a;">
                    <div style="font-size:.7rem;font-weight:700;color:#92400e;margin-bottom:4px;">EMPLOYEE REMARK</div>
                    <p id="dm-remark" class="mb-0" style="font-size:.82rem;color:#78350f;"></p>
                </div>
                <div class="mb-3">
                    <div style="font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:8px;">ATTACHED DOCUMENTS</div>
                    <div id="dm-files" class="d-flex flex-wrap gap-2"></div>
                </div>
                <div class="mb-3">
                    <div style="font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:8px;">REMARKS HISTORY</div>
                    <ul id="dm-remarks-list" class="list-group list-group-flush" style="border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;"></ul>
                </div>
                <div class="p-3 rounded-3" style="background:#f0f9ff;border:1px solid #bae6fd;">
                    <div style="font-size:.7rem;font-weight:700;color:#0369a1;margin-bottom:6px;">ADD NEW REMARK</div>
                    <textarea id="dm-new-remark" class="form-control form-control-sm" rows="2" placeholder="Type your remark…" aria-label="Type your remark…" style="border-radius:8px;font-size:.82rem;"></textarea>
                    <button class="dab dab-view mt-2" id="addRemarkBtn"><i class="ri-send-plane-line"></i> Submit Remark</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Running KM --}}
<div class="modal fade" id="runningModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="mcv-mhdr">
                <div class="mcv-mhdr-icon"><i class="ri-motorbike-line"></i></div>
                <h5>Vehicle Running — <span id="runModalMonth"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mcv-amt-strip mb-3">
                    <div class="mcv-amt-cell"><div class="lbl">Monthly Total KM</div><div class="val val-cl" id="runTotalKm">0</div></div>
                </div>
                <div class="table-responsive mb-3">
                    <table class="table mcv-tbl table-bordered mb-0">
                        <thead><tr><th>Date</th><th>Opening KM</th><th>Closing KM</th><th>Total KM</th><th>Status</th></tr></thead>
                        <tbody id="runTableBody"></tbody>
                    </table>
                </div>
                <div style="font-size:.72rem;font-weight:700;color:#64748b;margin-bottom:8px;">ACTIVITY LOG</div>
                <div class="table-responsive">
                    <table class="table mcv-tbl table-bordered mb-0">
                        <thead><tr><th>Action</th><th>Remark</th><th>Date</th></tr></thead>
                        <tbody id="runActivityBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Courier / Dispatch Modal (matching Flutter close_month.dart courier modal) --}}
<div class="modal fade" id="courierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="mcv-mhdr">
                <div class="mcv-mhdr-icon"><i class="ri-truck-line"></i></div>
                <h5 id="courierModalTitle">Hardcopy Courier Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="courierBody">
                <div class="mcv-loading"><div class="spinner-border spinner-border-sm text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

{{-- Single Claim Expense Detail Modal (matching Flutter view_open_month_single_claim.dart) --}}
<div class="modal fade" id="singleClaimDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="mcv-mhdr">
                <div class="mcv-mhdr-icon"><i class="ri-file-list-3-line"></i></div>
                <h5 id="sc-modal-title">Expense Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="sc-modal-body">
                <div class="mcv-loading"><div class="spinner-border spinner-border-sm text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

{{-- Fullscreen Image Lightbox --}}
<div id="mcvLightbox" class="mcv-lightbox" onclick="closeLightbox()">
    <div class="mcv-lightbox-box" onclick="event.stopPropagation()">
        <button type="button" class="mcv-lightbox-close" onclick="closeLightbox()" title="Close">&times;</button>
        <img id="mcvLightboxImg" class="mcv-lightbox-img" src="" alt="Enlarged Document">
    </div>
</div>

{{-- ══════════════════════════════
     SCRIPTS
     ══════════════════════════════ --}}
@push('scripts')
<script src="{{ asset('vendor/cdn/sweetalert2@11.26.25/dist/sweetalert2.all.min.js') }}"></script>
<script>
/* ═══════════════ CONFIG ═══════════════ */
(function() {
const _P = new URLSearchParams(window.location.search);
window.MCV = {
    ei: _P.get('ei')        || '{{ session("employee_id") }}',
    yr: _P.get('YearId')    || '{{ session("FYearId", session("year_id", 1)) }}',
    co: _P.get('CompanyId') || '{{ session("CompanyId", session("company_id", 1)) }}',
};
})();

const MN  = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const MNF = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

const fd = d => (!d||d==='0000-00-00') ? '—' : (() => {
    const dt = new Date(d);
    return `${String(dt.getDate()).padStart(2,'0')}/${String(dt.getMonth()+1).padStart(2,'0')}/${dt.getFullYear()}`;
})();
const rp = n => '₹' + Number(n||0).toLocaleString('en-IN');

function sbadge(filledOkay, claimStatus) {
    const s = String(filledOkay);
    if (s==='2') return '<span class="sbg sbg-denied">Denied</span>';
    if (s==='1') return '<span class="sbg sbg-verified">Verified</span>';
    const cs = claimStatus||'';
    if (cs==='Financed') return '<span class="sbg sbg-financed">Financed</span>';
    if (cs==='Approved') return '<span class="sbg sbg-approved">Approved</span>';
    return '<span class="sbg sbg-pending">Pending</span>';
}

/* ═══════════════ OPEN LIST ═══════════════ */
function loadOpen() {
    $('#openLoading').show(); $('#openList').empty();
    $.ajax({
        url: '{{ route("claimant.open.list") }}', method: 'GET',
        data: { ei: MCV.ei, YearId: MCV.yr, CompanyId: MCV.co },
        success(res) {
            $('#openLoading').hide();
            const list = res.Open_list || [];
            $('#cntOpen').text(list.length);
            if (!list.length) return emptyState('openList','ri-calendar-check-line','No open months this year');
            list.forEach((m, i) => renderOpenMonth(m, i===0));
        },
        error() { $('#openLoading').hide(); errorState('openList'); }
    });
}

function renderOpenMonth(it, expanded) {
    const mn  = MN[parseInt(it.Month)] || '?';
    const tot = it.TotalFilled_Claim_with_Amount  || {};
    const vfy = it.TotalVerify_Claim_with_Amount  || {};
    const nC  = tot.TotClaim||0;
    const nV  = tot.TotVerifiedClaims||0;
    const nD  = tot.TotDeniedClaims||0;
    const id  = `oM${it.Month}`;
    const status = it.MonthStatus||'Open';
    const chip = status==='Submitted'
        ? '<span class="mcv-chip mcv-chip-sub">Submitted</span>'
        : '<span class="mcv-chip mcv-chip-open">Open</span>';

    $('#openList').append(`
    <div class="month-tile">
        <div class="month-tile-header" data-target-collapse="${id}" aria-expanded="${expanded}">
            <div class="mcv-accent mcv-accent-open"></div>
            <div class="mtr-left">
                <div class="mtr-badge-wrap">
                    <div class="mcv-month-badge mcv-badge-open">${mn}</div>
                    ${chip}
                </div>
                <div class="mcv-month-body">
                    <div class="mcv-month-row1">
                        <span class="mcv-mi">Claims: <strong>${nC}</strong></span>
                        <span class="mcv-mi amt">Amt: <strong>${rp(tot.TotFilledAmt||0)}</strong></span>
                    </div>
                    <div class="mcv-month-row2">
                        <span class="mcv-sub mcv-sub-vr"><span class="mcv-sub-dot" style="background:#10b981;"></span>Verified: <strong>${rp(vfy.TotVerifyAmt||0)}</strong></span>
                        <span class="mcv-sub mcv-sub-vc"><span class="mcv-sub-dot" style="background:#3b82f6;"></span>Count: <strong>${nV}</strong></span>
                        <span class="mcv-sub mcv-sub-dc"><span class="mcv-sub-dot" style="background:#ef4444;"></span>Denied: <strong>${nD}</strong></span>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="mcv-month-right">
                    ${(String(it.submit_btn || it.Submit_btn || it.submitVisibility || '').trim().toUpperCase() === 'Y') ? `
                    <button class="mcv-ab mcv-ab-submit submit-month-btn"
                        data-month="${it.Month}"
                        data-empid="${it.EmployeeID}"
                        data-yearid="${it.YearId}"
                        data-comid="${it.ComId}"
                        data-totclaim="${nC}"
                        data-totfilledamt="${tot.TotFilledAmt||0}"
                        data-totverifiedclaims="${nV}"
                        data-totverifiedamt="${vfy.TotVerifyAmt||0}"
                        data-totdeniedclaims="${nD}"
                        title="Final Submit Month">
                        <i class="ri-checkbox-circle-line"></i> Submit
                    </button>` : ''}
                    <button class="mcv-ab mcv-ab-detail open-day-btn"
                        data-month="${it.Month}" data-totalclaim="${nC}"
                        data-filledamt="${tot.TotFilledAmt||0}"
                        data-verifiedclaim="${nV}" data-verifiedamt="${vfy.TotVerifyAmt||0}">
                        <i class="ri-eye-line"></i> Detail
                    </button>
                    <button class="mcv-ab mcv-ab-print" title="Print"
                        onclick="openPop('/expense-pdf?value=print_expensepdf&ComId=${it.ComId}&empid=${it.EmployeeID}&YearId=${it.YearId}&month=${it.Month}&nn=1&tokenid=${encodeURIComponent(it.Token||'')}')">
                        <i class="ri-printer-line"></i>
                    </button>
                    <button class="mcv-ab mcv-ab-km open-running-btn" title="Running KM"
                        data-empid="${it.EmployeeID}" data-yearid="${it.YearId}"
                        data-comid="${it.ComId}" data-month="${it.Month}">
                        <i class="ri-motorbike-line"></i>
                    </button>
                </div>
                <div class="mcv-chevron"><i class="ri-arrow-down-s-line"></i></div>
            </div>
        </div>
        <div class="collapse ${expanded?'show':''}" id="${id}">
            <div class="mcv-claims-body" id="claims-${id}">
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                </div>
            </div>
        </div>
    </div>`);

    loadDayWise(it.Month, `claims-${id}`, 'open');
}

/* ═══════════════ CLOSED LIST ═══════════════ */
function loadClosed(month) {
    $('#closedLoading').show(); $('#closedList').empty();
    $.ajax({
        url: '{{ route("claimant.close.list") }}', method: 'GET',
        data: { ei: MCV.ei, YearId: MCV.yr, CompanyId: MCV.co, month: month || '' },
        success(res) {
            $('#closedLoading').hide();
            const list = res.Close_list || [];
            $('#cntClosed').text(list.length);
            if (res.Closed_months && res.Closed_months.length && $('#closedMonthFilter option').length <= 1) {
                const sel = $('#closedMonthFilter');
                res.Closed_months.forEach(m => {
                    sel.append(`<option value="${m.Month_Number}">${m.Month_Name}</option>`);
                });
            }
            if (!list.length) return emptyState('closedList','ri-archive-2-line','No closed months found');
            list.forEach((m,i) => renderClosedMonth(m, i===0));
        },
        error() { $('#closedLoading').hide(); errorState('closedList'); }
    });
}

$(document).on('change', '#closedMonthFilter', function() {
    loadClosed($(this).val());
});

/* ═══════════════ PROGRESS STAGE STRIP ═══════════════ */
function buildStageStepper(it) {
    const has = d => !!d && d !== '0000-00-00';
    const isVVNR = String(it.ComId) === '11';
    let steps = [
        { label: 'Claimant', done: true, date: it.DateOfSubmit },
    ];
    const postStep     = { label: 'Post',     done: has(it.PostDate),      date: it.PostDate };
    const verifierStep = { label: 'Verifier', done: has(it.Verified_Date), date: it.Verified_Date };
    const approverStep = { label: 'Approver', done: has(it.Approved_Date), date: it.Approved_Date };
    const financeStep  = { label: 'Finance',  done: has(it.Fin_AppDate),   date: it.Fin_AppDate };

    steps = steps.concat(isVVNR
        ? [approverStep, financeStep, postStep, verifierStep]
        : [postStep, verifierStep, approverStep, financeStep]);

    return '<div class="mcv-stage-strip">' + steps.map(s => `
        <div class="mcv-stage ${s.done ? 'done' : ''}">
            <div class="mcv-stage-dot"><i class="${s.done ? 'ri-check-line' : 'ri-time-line'}"></i></div>
            <div class="mcv-stage-label">${s.label}</div>
            ${s.done && s.date ? `<div class="mcv-stage-date">${fd(s.date)}</div>` : ''}
        </div>`).join('') + '</div>';
}

function renderClosedMonth(it, expanded) {
    const mn = MN[parseInt(it.Month)]||'?';
    const id = `cM${it.Month}`;
    const posted = it.PostDate && it.PostDate !== '0000-00-00';

    $('#closedList').append(`
    <div class="closed-month-tile">
        <div class="month-tile-header" data-target-collapse="${id}" aria-expanded="${expanded}">
            <div class="mcv-accent mcv-accent-closed"></div>
            <div class="mtr-left">
                <div class="mtr-badge-wrap">
                    <div class="mcv-month-badge mcv-badge-closed">${mn}</div>
                    <span class="mcv-chip mcv-chip-closed">${it.payment_status||'Closed'}</span>
                </div>
                <div class="mcv-month-body">
                    <div class="mcv-month-row1">
                        <span class="mcv-mi">Claims: <strong>${it.Total_Claim||0}</strong></span>
                        <span class="mcv-mi amt">Amt: <strong>${rp(it.Claim_Amount||0)}</strong></span>
                    </div>
                    <div class="mcv-month-row2">
                        <span class="mcv-sub mcv-sub-vr"><span class="mcv-sub-dot" style="background:#10b981;"></span>Financed: <strong>${rp(it.Finance_Amount||0)}</strong></span>
                        <span class="mcv-sub mcv-sub-vc"><span class="mcv-sub-dot" style="background:#3b82f6;"></span>Approved: <strong>${rp(it.Approved_Amount||0)}</strong></span>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="mcv-month-right">
                    <button class="mcv-ab mcv-ab-detail close-day-btn"
                        data-month="${it.Month}"
                        data-claim_amount="${it.Claim_Amount||0}"
                        data-verified_amount="${it.Verified_Amount||0}"
                        data-approved_amount="${it.Approved_Amount||0}"
                        data-finance_amount="${it.Finance_Amount||0}">
                        <i class="ri-eye-line"></i> Detail
                    </button>
                    <button class="mcv-ab mcv-ab-print" title="Print"
                        onclick="openPop('{{ \App\Support\LegacyApi::url('pdfexpense.php') }}?value=print_expensepdf&ComId=${it.ComId}&empid=${it.EmployeeID}&YearId=${it.YearId}&month=${it.Month}&nn=2&tokenid=${encodeURIComponent(it.Token||'')}')">
                        <i class="ri-printer-line"></i>
                    </button>
                    <button class="mcv-ab mcv-ab-courier ${posted?'posted':''} courier-btn"
                        data-month="${it.Month}"
                        data-empid="${it.EmployeeID}"
                        data-yearid="${it.YearId}"
                        data-comid="${it.ComId}"
                        data-postdate="${it.PostDate||''}"
                        data-docateno="${it.DocateNo||''}"
                        data-agency="${it.Agency||''}"
                        data-recevingdate="${it.RecevingDate||it.ReceivingDate||''}"
                        data-docrmk="${it.DocRmk||''}"
                        data-cuimag="${it.CuImag||''}">
                        <i class="ri-truck-line"></i> ${posted?'Posted':'Courier'}
                    </button>
                </div>
                <div class="mcv-chevron"><i class="ri-arrow-down-s-line"></i></div>
            </div>
        </div>
        <div class="collapse ${expanded?'show':''}" id="${id}">
            ${buildStageStepper(it)}
            <div class="mcv-claims-body" id="claims-${id}">
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                </div>
            </div>
        </div>
    </div>`);

    loadDayWise(it.Month, `claims-${id}`, 'closed');
}

/* ═══════════════ PDF HELPER ═══════════════ */
function isPdfUrl(url) {
    if (!url) return false;
    const clean = String(url).split('?')[0].toLowerCase();
    return clean.endsWith('.pdf') || String(url).toLowerCase().includes('.pdf');
}

function getMediaPreviewHtml(it) {
    const isOdo = String(it.ClaimId) === '7';
    const opImg = it.opening_filepath || it.opening_filePath;
    const clImg = it.closing_filepath || it.closing_filePath;

    // Odometer dual photo support
    if (isOdo && opImg && clImg) {
        return `<div class="d-flex w-100 h-100" style="gap:2px;">
            <div style="flex:1;height:100%;overflow:hidden;position:relative;">
                <img src="${opImg}" class="zoom-img" alt="Opening KM" style="width:100%;height:100%;object-fit:cover;">
                <span style="position:absolute;bottom:3px;left:3px;background:rgba(0,0,0,0.65);color:#fff;font-size:9px;padding:1px 4px;border-radius:3px;font-weight:700;">OPEN</span>
            </div>
            <div style="flex:1;height:100%;overflow:hidden;position:relative;">
                <img src="${clImg}" class="zoom-img" alt="Closing KM" style="width:100%;height:100%;object-fit:cover;">
                <span style="position:absolute;bottom:3px;left:3px;background:rgba(0,0,0,0.65);color:#fff;font-size:9px;padding:1px 4px;border-radius:3px;font-weight:700;">CLOSE</span>
            </div>
        </div>`;
    }

    const files = [it.Img_1, it.Img_2, it.Img_3, it.pdf_1, it.pdf_2, opImg, clImg].filter(Boolean);
    if (!files.length) {
        return `<div class="cc-img-placeholder"><i class="ri-image-line"></i><span>No Image</span></div>`;
    }

    // Find first available file
    const primary = files[0];
    if (isPdfUrl(primary)) {
        return `<a href="${primary}" target="_blank" class="pdf-card-preview" onclick="event.stopPropagation();" title="Click to view PDF document">
            <div class="pdf-icon-badge"><i class="ri-file-pdf-2-line"></i></div>
            <span class="pdf-label">PDF Document</span>
        </a>`;
    }

    return `<img src="${primary}" alt="Bill" loading="lazy" onclick="event.stopPropagation();" class="zoom-img">`;
}

/* ═══════════════ DAY-WISE CLAIM CARDS ═══════════════ */
function loadDayWise(month, containerId, type) {
    const url = type==='open'
        ? '{{ route("claimant.open.day") }}'
        : '{{ route("claimant.close.day") }}';

    $.ajax({ url, method: 'GET',
        data: { month, ei: MCV.ei, YearId: MCV.yr, CompanyId: MCV.co },
        success(res) {
            const el = $(`#${containerId}`);
            const items = res.DayWiseDetails || [];
            if (!items.length) {
                el.html('<p class="text-center text-muted py-3 mb-0" style="font-size:.8rem;">No claims recorded this month.</p>');
                return;
            }
            let html = '<div class="claim-cards-row">';
            items.forEach(item => {
                const isOdo = String(item.ClaimId)==='7';
                const amt   = isOdo ? `${parseInt(item.TotKm||0)} KM` : rp(item.FilledTAmt||item.Claim_Amount||item.FilledAmt||0);
                const mediaHtml = getMediaPreviewHtml(item);

                const empRemark = item.DateEntryRemark || item.Remark || item.Claim_Remark || '';
                const vRemark   = item.VerifyRemark || item.Verifier_Remark || item.v_remark || '';
                const aRemark   = item.Approver_Remark || item.AppRemark || item.a_remark || '';
                const dReason   = item.Reason || item.DeniedRemark || item.DenyReason || '';

                let remarksHtml = '';
                if (empRemark && empRemark !== '—' && empRemark !== '0') {
                    remarksHtml += `<div class="cc-remark" title="Employee: ${empRemark.replace(/"/g,'&quot;')}"><i class="ri-chat-1-line"></i> ${empRemark}</div>`;
                }
                if (vRemark && vRemark !== '—' && vRemark !== '0') {
                    remarksHtml += `<div class="cc-remark-verifier" title="Verifier: ${vRemark.replace(/"/g,'&quot;')}"><i class="ri-check-line"></i> <strong>Vfy:</strong> ${vRemark}</div>`;
                }
                if (aRemark && aRemark !== '—' && aRemark !== '0') {
                    remarksHtml += `<div class="cc-remark-approver" title="Approver: ${aRemark.replace(/"/g,'&quot;')}"><i class="ri-check-double-line"></i> <strong>App:</strong> ${aRemark}</div>`;
                }
                if (dReason && dReason !== '—' && dReason !== '0') {
                    remarksHtml += `<div class="cc-remark-denied" title="Denied: ${dReason.replace(/"/g,'&quot;')}"><i class="ri-close-line"></i> <strong>Deny:</strong> ${dReason}</div>`;
                }

                const verifiedAmt = item.VerifyTAmt || item.Verified_Amount || item.VerifyAmount || item.TotVSubAmt;
                const approvedAmt = item.AppTAmt || item.Approved_Amount || item.AppAmt;
                const financedAmt = item.Finance_Amount || item.FinancedTAmt || item.FinPayAmt;

                const claimDate    = fd(item.BillDate || item.CrDate || item.DateOfSubmit);
                const verifiedDate = (item.Verified_Date && item.Verified_Date !== '0000-00-00') ? fd(item.Verified_Date) : ((item.VerifyDate && item.VerifyDate !== '0000-00-00') ? fd(item.VerifyDate) : '');
                const approvedDate = (item.Approved_Date && item.Approved_Date !== '0000-00-00') ? fd(item.Approved_Date) : '';
                const financeDate  = (item.Fin_AppDate && item.Fin_AppDate !== '0000-00-00') ? fd(item.Fin_AppDate) : '';

                let detailRows = `<div class="cc-amt-row"><span class="lbl">Claimed:</span> <div><strong class="text-primary">${amt}</strong> <span class="cc-amt-date">(${claimDate})</span></div></div>`;
                if (verifiedAmt && parseFloat(verifiedAmt) > 0) {
                    detailRows += `<div class="cc-amt-row"><span class="lbl">Verified:</span> <div><strong class="text-success">${rp(verifiedAmt)}</strong> ${verifiedDate ? `<span class="cc-amt-date">(${verifiedDate})</span>` : ''}</div></div>`;
                }
                if (approvedAmt && parseFloat(approvedAmt) > 0) {
                    detailRows += `<div class="cc-amt-row"><span class="lbl">Approved:</span> <div><strong style="color:#059669;">${rp(approvedAmt)}</strong> ${approvedDate ? `<span class="cc-amt-date">(${approvedDate})</span>` : ''}</div></div>`;
                }
                if (financedAmt && parseFloat(financedAmt) > 0) {
                    detailRows += `<div class="cc-amt-row"><span class="lbl">Financed:</span> <div><strong style="color:#047857;">${rp(financedAmt)}</strong> ${financeDate ? `<span class="cc-amt-date">(${financeDate})</span>` : ''}</div></div>`;
                }

                html += `
                <div class="claim-card-item">
                    <div class="cc-img-wrap">
                        ${mediaHtml}
                        <div class="cc-badge-overlay">${sbadge(item.FilledOkay, item.ClaimStatus)}</div>
                    </div>
                    <div class="cc-info">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="mcv-exp-id">#${item.ExpId}</span>
                            <span class="claim-date">${fd(item.BillDate)}</span>
                        </div>
                        <div class="claim-card-type" title="${item.ClaimName||''}">${item.ClaimName||'—'}</div>
                        <div class="cc-amt-detail">
                            ${detailRows}
                        </div>
                        ${remarksHtml}
                    </div>
                    <div class="claim-card-footer">
                        <button class="claim-view-link open-single-detail-btn"
                            data-expid="${item.ExpId}" data-month="${month}" data-type="${type}">
                            <i class="ri-eye-line"></i> View Details
                        </button>
                    </div>
                </div>`;
            });
            html += '</div>';
            el.html(html);
        },
        error() {
            $(`#${containerId}`).html('<p class="text-danger text-center py-2 mb-0" style="font-size:.8rem;">Failed to load.</p>');
        }
    });
}

/* ═══════════════ DRAFTS ═══════════════ */
function loadDrafts() {
    $('#draftsLoading').show(); $('#draftsList').empty();
    $.ajax({
        url: '{{ route("claimant.draft.list") }}', method: 'GET',
        data: { ei: MCV.ei, YearId: MCV.yr, CompanyId: MCV.co },
        success(res) {
            $('#draftsLoading').hide();
            const list = res.Draft_list || [];
            $('#cntDraft').text(list.length);
            if (!list.length) return emptyState('draftsList','ri-draft-line','No draft bills uploaded');
            list.forEach(renderDraft);
        },
        error() { $('#draftsLoading').hide(); errorState('draftsList'); }
    });
}

function renderDraft(it) {
    const rmJ  = JSON.stringify(it.remarks||[]).replace(/'/g,"&#39;");
    const imgJ = JSON.stringify({img1:it.Img_1,img2:it.Img_2,img3:it.Img_3,pdf1:it.pdf_1,pdf2:it.pdf_2}).replace(/'/g,"&#39;");

    const topMediaHtml = getMediaPreviewHtml(it);

    // Extra thumbs
    let extraThumbs = '';
    const allFiles = [it.Img_1, it.Img_2, it.Img_3, it.pdf_1, it.pdf_2].filter(Boolean);
    if (allFiles.length > 1) {
        allFiles.slice(1).forEach(f => {
            if (isPdfUrl(f)) {
                extraThumbs += `<a href="${f}" target="_blank" class="mcv-pdf-thumb" title="Open PDF" onclick="event.stopPropagation();"><i class="ri-file-pdf-2-fill"></i></a>`;
            } else {
                extraThumbs += `<img alt="Click to view" src="${f}" class="draft-extra-thumb zoom-img" title="Click to view">`;
            }
        });
    }

    const remarkText = it.DateEntryRemark || it.Remark || '';
    const remarkHtml = (remarkText && remarkText !== '—' && remarkText !== '0')
        ? `<div class="draft-remark" title="${remarkText.replace(/"/g,'&quot;')}"><i class="ri-chat-1-line"></i> ${remarkText}</div>`
        : '';

    $('#draftsList').append(`
    <div class="draft-bill-card" id="draftCard-${it.ExpId}">
        <div class="draft-img-wrap">${topMediaHtml}</div>
        <div class="draft-accent-bar"></div>
        ${extraThumbs ? `<div class="draft-extra-thumbs">${extraThumbs}</div>` : ''}
        <div class="draft-info">
            <div class="dbh-top">
                <span class="mcv-exp-id">#${it.ExpId}</span>
                <span class="sbg sbg-draft">Draft</span>
            </div>
            <div class="draft-type mt-1" title="${it.ClaimName||''}">${it.ClaimName||'Expense Bill'}</div>
            <div class="draft-meta">Bill Date: ${fd(it.BillDate)}</div>
            <div class="draft-amount mt-1">${rp(it.FilledAmt||it.FilledTAmt||0)} <small class="text-muted fw-normal" style="font-size:0.65rem;">(${fd(it.CrDate||it.BillDate)})</small></div>
            ${remarkHtml}
        </div>
        <div class="draft-bill-actions">
            <button class="dab dab-view view-draft-btn"
                data-exp-id="${it.ExpId}"
                data-claimtype="${it.ClaimName||''}"
                data-amount="${it.FilledAmt||it.FilledTAmt||0}"
                data-emp-remark="${(it.DateEntryRemark||'').replace(/"/g,'&quot;')}"
                data-remarks='${rmJ}'
                data-images='${imgJ}'>
                <i class="ri-eye-line"></i> View
            </button>
            <button class="dab dab-del delete-draft-btn" data-exp-id="${it.ExpId}">
                <i class="ri-delete-bin-line"></i>
            </button>
        </div>
    </div>`);
}

/* ═══════════════ DAY DETAIL MODAL ═══════════════ */
$(document).on('click', '.open-day-btn', function(e) {
    e.stopPropagation();
    const m  = $(this).data('month');
    const nC = $(this).data('totalclaim');
    const fa = $(this).data('filledamt');
    const nV = $(this).data('verifiedclaim');
    const va = $(this).data('verifiedamt');
    showDayDetailModal(m, 'open', nC, fa, nV, va, null, null);
});

$(document).on('click', '.close-day-btn', function(e) {
    e.stopPropagation();
    const m = $(this).data('month');
    showDayDetailModal(m, 'closed', null,
        $(this).data('claim_amount'), null,
        $(this).data('verified_amount'),
        $(this).data('approved_amount'),
        $(this).data('finance_amount'));
});

function showDayDetailModal(month, type, totClaim, claimedAmt, verifiedClaim, verifiedAmt, approvedAmt, financedAmt) {
    const mn = MNF[parseInt(month)] || ('Month ' + month);
    $('#ddModalTitle').text(`Claims Breakdown — ${mn} (${type === 'open' ? 'Open Month' : 'Closed Month'})`);
    $('#ddBody').html('<div class="mcv-loading"><div class="spinner-border spinner-border-sm text-primary"></div></div>');

    let amtStrip = `
    <div class="mcv-amt-strip mb-3">
        <div class="mcv-amt-cell"><div class="lbl">Claimed</div><div class="val val-cl">${rp(claimedAmt||0)}</div></div>
        <div class="mcv-amt-cell"><div class="lbl">Verified</div><div class="val val-vf">${verifiedAmt ? rp(verifiedAmt) : '—'}</div></div>
        <div class="mcv-amt-cell"><div class="lbl">Approved</div><div class="val val-ap">${approvedAmt ? rp(approvedAmt) : '—'}</div></div>
        <div class="mcv-amt-cell"><div class="lbl">Financed</div><div class="val val-fn">${financedAmt ? rp(financedAmt) : '—'}</div></div>
    </div>`;
    $('#ddAmtStrip').html(amtStrip).show();

    new bootstrap.Modal(document.getElementById('dayDetailModal')).show();

    const url = type === 'open' ? '{{ route("claimant.open.day") }}' : '{{ route("claimant.close.day") }}';
    $.ajax({
        url, method: 'GET',
        data: { month, ei: MCV.ei, YearId: MCV.yr, CompanyId: MCV.co },
        success(res) {
            const items = res.DayWiseDetails || [];
            if (!items.length) {
                $('#ddBody').html('<p class="text-center text-muted py-4 mb-0">No itemized claims found for this month.</p>');
                return;
            }
            let rows = '';
            items.forEach(it => {
                const isOdo = String(it.ClaimId) === '7';
                const clmAmt = isOdo ? `${parseInt(it.TotKm||0)} KM` : rp(it.FilledTAmt||it.Claim_Amount||it.FilledAmt||0);
                const vfAmt = it.VerifyTAmt || it.Verified_Amount || it.VerifyAmount || it.TotVSubAmt;
                const apAmt = it.AppTAmt || it.Approved_Amount || it.AppAmt;
                const fnAmt = it.Finance_Amount || it.FinancedTAmt || it.FinPayAmt;

                const cDate = fd(it.BillDate || it.CrDate);
                const vDate = (it.Verified_Date && it.Verified_Date !== '0000-00-00') ? fd(it.Verified_Date) : '';
                const aDate = (it.Approved_Date && it.Approved_Date !== '0000-00-00') ? fd(it.Approved_Date) : '';
                const fDate = (it.Fin_AppDate && it.Fin_AppDate !== '0000-00-00') ? fd(it.Fin_AppDate) : '';

                const rmk = it.DateEntryRemark || it.Remark || it.Claim_Remark || '—';
                const vRmk = it.VerifyRemark || it.Verifier_Remark || '';

                rows += `
                <tr>
                    <td><span class="mcv-exp-id">#${it.ExpId}</span></td>
                    <td class="fw-semibold text-nowrap">${fd(it.BillDate)}</td>
                    <td class="fw-bold">${it.ClaimName || 'Expense'}</td>
                    <td>
                        <strong class="text-primary">${clmAmt}</strong>
                        <div class="text-muted" style="font-size:0.65rem;">${cDate}</div>
                    </td>
                    <td>
                        <strong class="text-success">${vfAmt ? rp(vfAmt) : '—'}</strong>
                        ${vDate ? `<div class="text-success" style="font-size:0.65rem;">${vDate}</div>` : ''}
                    </td>
                    <td>
                        <strong style="color:#059669;">${apAmt ? rp(apAmt) : '—'}</strong>
                        ${aDate ? `<div class="text-primary" style="font-size:0.65rem;">${aDate}</div>` : ''}
                    </td>
                    <td style="font-size:0.75rem;max-width:180px;">
                        ${rmk !== '—' ? `<div class="text-truncate text-muted mb-1" title="${rmk}"><i class="ri-chat-1-line text-warning"></i> ${rmk}</div>` : ''}
                        ${vRmk ? `<div class="text-truncate text-primary" title="${vRmk}"><i class="ri-check-line text-success"></i> ${vRmk}</div>` : ''}
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-light border rounded-pill px-2 py-1 open-single-detail-btn"
                            data-expid="${it.ExpId}" data-month="${month}" data-type="${type}" style="font-size:0.72rem;font-weight:700;">
                            <i class="ri-eye-line text-primary"></i> View
                        </button>
                    </td>
                </tr>`;
            });

            $('#ddBody').html(`
                <div class="table-responsive">
                    <table class="table mcv-tbl table-hover table-bordered mb-0 align-middle" style="font-size:0.8rem;">
                        <thead>
                            <tr>
                                <th>Exp ID</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Claimed (Amt & Date)</th>
                                <th>Verified (Amt & Date)</th>
                                <th>Approved (Amt & Date)</th>
                                <th>Remarks</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `);
        },
        error() {
            $('#ddBody').html('<p class="text-danger text-center py-4 mb-0">Failed to load day-wise details.</p>');
        }
    });
}

$(document).on('click', '.open-single-detail-btn', function(e) {
    e.stopPropagation();
    const expId = $(this).data('expid');
    const month = $(this).data('month');
    const type  = $(this).data('type') || 'open';
    openExpenseDetailModal(expId, month, type);
});

function openExpenseDetailModal(expId, month, type) {
    $('#sc-modal-title').text(`Expense Details — #${expId}`);
    $('#sc-modal-body').html('<div class="mcv-loading"><div class="spinner-border spinner-border-sm text-primary"></div></div>');
    new bootstrap.Modal(document.getElementById('singleClaimDetailModal')).show();

    $.ajax({
        url: '{{ route("claimant.expense.detail") }}',
        method: 'GET',
        data: { expid: expId, ei: MCV.ei, YearId: MCV.yr, CompanyId: MCV.co },
        success(res) {
            const data = res.details || res.expenses_summary || res || {};
            const amt = data.Amount || data.FilledAmt || data.FilledTAmt || 0;
            const rmks = data.Remarks || res.Remarks || [];
            const exps = data.expenses || res.expenses || [];
            const isOdo = String(data.ClaimId) === '7';

            // Document Carousel / Viewer matching Flutter view_close_month_single_claim
            const allDocs = [
                { url: data.opening_filepath, label: 'Opening KM' },
                { url: data.closing_filepath, label: 'Closing KM' },
                { url: data.Img_1, label: 'Bill Photo 1' },
                { url: data.Img_2, label: 'Bill Photo 2' },
                { url: data.Img_3, label: 'Bill Photo 3' },
                { url: data.pdf_1, label: 'PDF Document 1' },
                { url: data.pdf_2, label: 'PDF Document 2' }
            ].filter(d => !!d.url);

            let viewerHtml = '';
            if (allDocs.length) {
                const primaryDoc = allDocs[0].url;
                const isPdf = isPdfUrl(primaryDoc);

                let thumbPills = '';
                allDocs.forEach((d, idx) => {
                    const isDocPdf = isPdfUrl(d.url);
                    thumbPills += `<button type="button" class="btn btn-sm ${idx===0?'btn-primary':'btn-outline-secondary'} sc-thumb-select-btn rounded-pill px-3 py-1"
                        data-url="${d.url}" data-is-pdf="${isDocPdf}" style="font-size:0.72rem;font-weight:700;">
                        <i class="${isDocPdf?'ri-file-pdf-2-fill text-danger':'ri-image-line'} me-1"></i> ${d.label}
                    </button>`;
                });

                viewerHtml = `
                <div class="sc-viewer-card p-3 mb-3 text-center rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span style="font-size:0.72rem;font-weight:700;color:#64748b;text-transform:uppercase;">ATTACHED DOCUMENT VIEWER</span>
                        <button type="button" class="btn btn-sm btn-light border rounded-circle shadow-sm" id="scRotateBtn" title="Rotate Image 90°" style="width:32px;height:32px;padding:0;">
                            <i class="ri-anticlockwise-2-line text-primary"></i>
                        </button>
                    </div>
                    <div class="sc-primary-box position-relative d-flex align-items-center justify-content-center rounded-2 overflow-hidden mx-auto"
                         style="max-width:100%;height:230px;background:#0f172a10;">
                        ${isPdf ? `
                            <a href="${primaryDoc}" target="_blank" class="pdf-card-preview" title="Click to open PDF in new tab">
                                <div class="pdf-icon-badge"><i class="ri-file-pdf-2-line"></i></div>
                                <span class="pdf-label">Open Full PDF Document</span>
                            </a>
                        ` : `
                            <img id="scPrimaryImg" src="${primaryDoc}" class="zoom-img" alt="Document"
                                 style="max-width:100%;max-height:100%;object-fit:contain;cursor:zoom-in;transition:transform 0.2s ease;">
                        `}
                    </div>
                    ${allDocs.length > 1 ? `
                    <div class="d-flex flex-wrap gap-2 justify-content-center mt-2">
                        ${thumbPills}
                    </div>` : ''}
                </div>`;
            }

            // Financial Breakdown Row with corresponding dates
            const claimedVal  = isOdo ? `${parseInt(data.TotKm||0)} KM` : rp(amt);
            const verifiedVal = data.VerifyTAmt || data.Verified_Amount || data.VerifyAmount || data.TotVSubAmt;
            const approvedVal = data.AppTAmt || data.Approved_Amount || data.AppAmt;
            const financedVal = data.Finance_Amount || data.FinancedTAmt || data.FinPayAmt;

            const claimDate    = fd(data.CrDate || data.DateOfSubmit || data.BillDate);
            const verifiedDate = (data.Verified_Date && data.Verified_Date !== '0000-00-00') ? fd(data.Verified_Date) : ((data.VerifyDate && data.VerifyDate !== '0000-00-00') ? fd(data.VerifyDate) : '');
            const approvedDate = (data.Approved_Date && data.Approved_Date !== '0000-00-00') ? fd(data.Approved_Date) : '';
            const financeDate  = (data.Fin_AppDate && data.Fin_AppDate !== '0000-00-00') ? fd(data.Fin_AppDate) : '';

            // Staggered Key-Value Details Grid (mirrors Flutter view_open_month_single_claim.dart lines 550-650)
            let detailsGridHtml = '';
            const ignoredKeys = ['particulars', 'particular', 'expenses', 'remarks', 'img_1', 'img_2', 'img_3', 'pdf_1', 'pdf_2', 'opening_filepath', 'closing_filepath', 'code', 'msg'];
            let detailItems = [];
            Object.keys(data).forEach(k => {
                if (ignoredKeys.includes(k.toLowerCase()) || typeof data[k] === 'object' || data[k] === null || data[k] === '') return;
                let val = data[k];
                if (k.toLowerCase().includes('date') && val !== '0000-00-00') val = fd(val);
                if (k.toLowerCase().includes('amount') || k.toLowerCase().includes('amt')) {
                    if (!isNaN(val) && parseFloat(val) > 0) val = rp(val);
                }
                let label = k.replace(/([A-Z])/g, ' $1').replace(/_/g, ' ').trim();
                label = label.charAt(0).toUpperCase() + label.slice(1);
                detailItems.push({ label, val });
            });

            if (detailItems.length) {
                let gridRows = '';
                detailItems.forEach((it, idx) => {
                    gridRows += `
                    <div class="col-6 col-md-4">
                        <div class="p-2 rounded-2 ${idx % 2 === 0 ? 'bg-light' : 'bg-white border'}" style="font-size:0.78rem;">
                            <span class="text-muted d-block" style="font-size:0.68rem;font-weight:700;text-transform:uppercase;">${it.label}</span>
                            <strong class="text-dark d-block text-truncate" title="${it.val}">${it.val}</strong>
                        </div>
                    </div>`;
                });
                detailsGridHtml = `
                <div class="mb-3">
                    <div style="font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:8px;text-transform:uppercase;">CLAIM METADATA & SPECIFICATIONS</div>
                    <div class="row g-2">${gridRows}</div>
                </div>`;
            }

            // Particulars table
            let particularsHtml = '';
            if (exps && exps.length) {
                let pRows = '';
                exps.forEach(p => {
                    pRows += `<tr>
                        <td><strong>${p.Title || p.ExpType || '—'}</strong></td>
                        <td style="font-size:.78rem;color:#64748b;">${p.Remark || '—'}</td>
                        <td class="text-end fw-bold text-primary">${rp(p.Amount || 0)}</td>
                    </tr>`;
                });
                particularsHtml = `
                <div class="mb-3">
                    <div style="font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:8px;text-transform:uppercase;">ITEMIZED PARTICULARS</div>
                    <div class="table-responsive">
                        <table class="table mcv-tbl table-bordered mb-0">
                            <thead><tr><th>Particular</th><th>Remark</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>${pRows}</tbody>
                        </table>
                    </div>
                </div>`;
            }

            // Remarks history timeline (Chat bubble style matching Flutter lines 820-900)
            let rmksHtml = '';
            if (rmks && rmks.length) {
                rmks.forEach(r => {
                    const isEmployee = String(r.sent_by) === '10' || (r.role && r.role.toLowerCase().includes('claimant'));
                    rmksHtml += `
                    <div class="d-flex flex-column ${isEmployee ? 'align-items-start' : 'align-items-end'} mb-2">
                        <div class="p-2 rounded-3 shadow-sm" style="max-width:85%;background:${isEmployee ? '#f8fafc' : '#eff6ff'};border:1px solid ${isEmployee ? '#e2e8f0' : '#bfdbfe'};">
                            <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
                                <strong style="font-size:.74rem;color:${isEmployee ? '#1e293b' : '#1d4ed8'};">${r.emp_name || r.name || (isEmployee ? 'Employee' : 'Reviewer')}</strong>
                                <small class="text-muted" style="font-size:.66rem;">${r.CrDateTime ? formatDateTime(r.CrDateTime) : (r.remark_date || '')}</small>
                            </div>
                            <div style="font-size:.82rem;color:#334155;">${r.Remark || r.remark || '—'}</div>
                        </div>
                    </div>`;
                });
            } else {
                rmksHtml = '<div class="text-muted text-center py-3" style="font-size:.8rem;">No conversation remarks yet.</div>';
            }

            const empRemark = data.Remark || data.DateEntryRemark || data.Claim_Remark;
            const vfyRemark = data.VerifyRemark || data.Verifier_Remark;
            const appRemark = data.Approver_Remark || data.AppRemark;
            const denReason = data.Reason || data.DeniedRemark || data.DenyReason;

            const html = `
            ${viewerHtml}
            <div class="row g-2 mb-3">
                <div class="col-3">
                    <div class="text-center p-2 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                        <div style="font-size:.62rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">Exp ID</div>
                        <div class="mt-1"><span class="mcv-exp-id">#${expId}</span></div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="text-center p-2 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                        <div style="font-size:.62rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">Category</div>
                        <div class="fw-bold mt-1 text-truncate" style="font-size:.82rem;color:#1e293b;" title="${data.ClaimName||''}">${data.ClaimName || 'Expense'}</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="text-center p-2 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                        <div style="font-size:.62rem;font-weight:700;color:#94a3b8;text-transform:uppercase;">Bill Date</div>
                        <div class="fw-bold mt-1" style="font-size:.82rem;color:#1e293b;">${fd(data.BillDate)}</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="text-center p-2 rounded-3" style="background:#eff6ff;border:1px solid #bfdbfe;">
                        <div style="font-size:.62rem;font-weight:700;color:#1d4ed8;text-transform:uppercase;">Claimed Amt</div>
                        <div class="fw-bold text-primary mt-1">${claimedVal}</div>
                    </div>
                </div>
            </div>

            <div class="mcv-amt-strip mb-3">
                <div class="mcv-amt-cell">
                    <div class="lbl">Claimed</div>
                    <div class="val val-cl">${claimedVal}</div>
                    <div class="text-muted" style="font-size:0.65rem;margin-top:2px;">${claimDate}</div>
                </div>
                <div class="mcv-amt-cell">
                    <div class="lbl">Verified</div>
                    <div class="val val-vf">${verifiedVal ? rp(verifiedVal) : '—'}</div>
                    <div class="text-success" style="font-size:0.65rem;margin-top:2px;">${verifiedDate ? verifiedDate : (verifiedVal ? 'Verified' : 'Pending')}</div>
                </div>
                <div class="mcv-amt-cell">
                    <div class="lbl">Approved</div>
                    <div class="val val-ap">${approvedVal ? rp(approvedVal) : '—'}</div>
                    <div class="text-primary" style="font-size:0.65rem;margin-top:2px;">${approvedDate ? approvedDate : (approvedVal ? 'Approved' : 'Pending')}</div>
                </div>
                <div class="mcv-amt-cell">
                    <div class="lbl">Financed</div>
                    <div class="val val-fn">${financedVal ? rp(financedVal) : '—'}</div>
                    <div class="text-emerald" style="font-size:0.65rem;margin-top:2px;">${financeDate ? financeDate : (financedVal ? 'Financed' : 'Pending')}</div>
                </div>
            </div>

            ${empRemark ? `
            <div class="mb-2 p-3 rounded-3" style="background:#fffbeb;border:1px solid #fde68a;">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span style="font-size:.7rem;font-weight:700;color:#92400e;"><i class="ri-chat-1-line"></i> EMPLOYEE REMARK</span>
                    <small class="text-muted" style="font-size:0.66rem;">${claimDate}</small>
                </div>
                <p class="mb-0" style="font-size:.82rem;color:#78350f;">${empRemark}</p>
            </div>` : ''}

            ${vfyRemark ? `
            <div class="mb-2 p-3 rounded-3" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span style="font-size:.7rem;font-weight:700;color:#166534;"><i class="ri-check-line"></i> VERIFIER REMARK</span>
                    <small class="text-success" style="font-size:0.66rem;">${verifiedDate || ''}</small>
                </div>
                <p class="mb-0" style="font-size:.82rem;color:#14532d;">${vfyRemark}</p>
            </div>` : ''}

            ${appRemark ? `
            <div class="mb-2 p-3 rounded-3" style="background:#faf5ff;border:1px solid #e9d5ff;">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span style="font-size:.7rem;font-weight:700;color:#6b21a8;"><i class="ri-check-double-line"></i> APPROVER REMARK</span>
                    <small class="text-primary" style="font-size:0.66rem;">${approvedDate || ''}</small>
                </div>
                <p class="mb-0" style="font-size:.82rem;color:#581c87;">${appRemark}</p>
            </div>` : ''}

            ${denReason ? `
            <div class="mb-2 p-3 rounded-3" style="background:#fef2f2;border:1px solid #fecaca;">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span style="font-size:.7rem;font-weight:700;color:#991b1b;"><i class="ri-close-circle-line"></i> DENIAL / REJECTION REASON</span>
                </div>
                <p class="mb-0" style="font-size:.82rem;color:#7f1d1d;">${denReason}</p>
            </div>` : ''}

            ${detailsGridHtml}
            ${particularsHtml}

            <div class="mb-3">
                <div style="font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:8px;text-transform:uppercase;">REMARKS CONVERSATION TIMELINE</div>
                <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;max-height:220px;overflow-y:auto;">${rmksHtml}</div>
            </div>

            <div class="p-3 rounded-3" style="background:#f0f9ff;border:1px solid #bae6fd;">
                <div style="font-size:.7rem;font-weight:700;color:#0369a1;margin-bottom:6px;text-transform:uppercase;">ADD REMARK</div>
                <textarea id="sc-new-remark" class="form-control form-control-sm" rows="2" placeholder="Type your remark here…" aria-label="Type your remark here…" style="border-radius:8px;font-size:.82rem;"></textarea>
                <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                    <button class="dab dab-view" id="sc-add-remark-btn" data-exp-id="${expId}"><i class="ri-send-plane-line"></i> Submit Remark</button>
                    ${MCV.isVer ? `
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-success rounded-3 px-3 py-1 btn-verify-claim" data-exp-id="${expId}"><i class="ri-check-line me-1"></i>Verify</button>
                        <button class="btn btn-sm btn-outline-danger rounded-3 px-3 py-1 btn-deny-claim" data-exp-id="${expId}"><i class="ri-close-line me-1"></i>Deny</button>
                    </div>` : ''}
                </div>
            </div>`;

            $('#sc-modal-body').html(html);
        },
        error() {
            $('#sc-modal-body').html('<div class="alert alert-danger py-2 small">Failed to load expense details.</div>');
        }
    });
}

// Rotate & image selector handlers in Single Claim Modal
let currentScRotation = 0;
$(document).on('click', '#scRotateBtn', function() {
    currentScRotation = (currentScRotation + 90) % 360;
    $('#scPrimaryImg').css('transform', `rotate(${currentScRotation}deg)`);
});

$(document).on('click', '.sc-thumb-select-btn', function() {
    $('.sc-thumb-select-btn').removeClass('btn-primary').addClass('btn-outline-secondary');
    $(this).removeClass('btn-outline-secondary').addClass('btn-primary');
    const url = $(this).data('url');
    const isPdf = $(this).data('is-pdf');
    currentScRotation = 0;

    if (isPdf) {
        $('.sc-primary-box').html(`
            <a href="${url}" target="_blank" class="pdf-card-preview" title="Click to open PDF in new tab">
                <div class="pdf-icon-badge"><i class="ri-file-pdf-2-line"></i></div>
                <span class="pdf-label">Open Full PDF Document</span>
            </a>
        `);
    } else {
        $('.sc-primary-box').html(`
            <img id="scPrimaryImg" src="${url}" class="zoom-img" alt="Document"
                 style="max-width:100%;max-height:100%;object-fit:contain;cursor:zoom-in;transition:transform 0.2s ease;">
        `);
    }
});




/* ═══════════════ DRAFT MODAL ═══════════════ */
$(document).on('click', '.view-draft-btn', function() {
    const imgs = JSON.parse($(this).attr('data-images')||'{}');
    const rmks = JSON.parse($(this).attr('data-remarks')||'[]');
    const expId = $(this).data('exp-id');

    $('#dm-expid').text('#'+expId);
    $('#dm-type').text($(this).data('claimtype'));
    $('#dm-amount').text(rp($(this).data('amount')));
    $('#dm-remark').text($(this).data('emp-remark')||'No remark added.');
    $('#addRemarkBtn').attr('data-exp-id', expId);

    const fl = $('#dm-files').empty();
    const allDraftFiles = [imgs.img1, imgs.img2, imgs.img3, imgs.pdf1, imgs.pdf2].filter(Boolean);
    allDraftFiles.forEach(f => {
        if (isPdfUrl(f)) {
            fl.append(`<a href="${f}" target="_blank" class="mcv-pdf-chip" title="Open PDF Document">
                <i class="ri-file-pdf-2-fill"></i> View PDF
            </a>`);
        } else {
            fl.append(`<div class="dm-file-chip zoom-img" data-img="${f}" title="Click to enlarge">
                <img src="${f}" alt="doc">
            </div>`);
        }
    });
    if (!fl.children().length) fl.html('<span class="text-muted" style="font-size:.78rem;">No documents uploaded.</span>');

    const rl = $('#dm-remarks-list').empty();
    rmks.forEach(r => rl.append(`<li class="list-group-item py-2 px-3">
        <small class="text-muted d-block" style="font-size:.7rem;">${r.remark_date||''}</small>
        <span style="font-size:.82rem;">${r.remark||'—'}</span></li>`));
    if (!rmks.length) rl.append('<li class="list-group-item text-muted text-center py-3" style="font-size:.8rem;">No remarks yet.</li>');

    $('#dm-new-remark').val('');
    new bootstrap.Modal(document.getElementById('draftModal')).show();
});

$(document).on('click', '#addRemarkBtn', function() {
    const id = $(this).attr('data-exp-id');
    const rm = $('#dm-new-remark').val().trim();
    if (!rm) { Swal.fire('Warning','Please enter a remark.','warning'); return; }
    $.ajax({
        url: '{{ route("claimant.add.remark") }}', method: 'POST',
        data: { _token: '{{ csrf_token() }}', exp_id: id, remark: rm },
        success(res) {
            if (res.Code==='200' || (res.msg||'').toLowerCase().includes('success')) {
                Swal.fire({ icon:'success', title:'Remark Added!', timer:1400, showConfirmButton:false });
                $('#dm-remarks-list').append(`<li class="list-group-item py-2 px-3">
                    <small class="text-muted d-block" style="font-size:.7rem;">Just Now</small>
                    <span style="font-size:.82rem;">${rm}</span></li>`);
                $('#dm-new-remark').val('');
            } else Swal.fire('Error', res.msg||'Failed', 'error');
        },
        error() { Swal.fire('Error','Server error','error'); }
    });
});

$(document).on('click', '.delete-draft-btn', function() {
    const id = $(this).data('exp-id');
    Swal.fire({
        title: 'Delete Draft?', text: 'This cannot be undone.',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#dc2626', confirmButtonText: 'Yes, Delete'
    }).then(r => {
        if (!r.isConfirmed) return;
        $.ajax({
            url: '{{ route("claimant.draft.delete") }}', method: 'POST',
            data: { _token: '{{ csrf_token() }}', exp_id: id },
            success(res) {
                if (res.Code==='300' || (res.msg||'').toLowerCase().includes('deleted')) {
                    Swal.fire({ icon:'success', title:'Deleted!', timer:1200, showConfirmButton:false });
                    $(`#draftCard-${id}`).fadeOut(300, function(){ $(this).remove(); });
                    const n = Math.max(0, parseInt($('#cntDraft').text())-1);
                    $('#cntDraft').text(n);
                } else Swal.fire('Error', res.msg||'Failed', 'error');
            },
            error() { Swal.fire('Error','Server error','error'); }
        });
    });
});

/* ═══════════════ RUNNING KM ═══════════════ */
$(document).on('click', '.open-running-btn', function(e) {
    e.stopPropagation();
    const {empid, yearid, comid, month} = $(this).data();
    $('#runModalMonth').text(MNF[parseInt(month)]||'');
    $('#runTableBody,#runActivityBody').html('<tr><td colspan="5" class="text-center text-muted py-3">Loading…</td></tr>');
    new bootstrap.Modal(document.getElementById('runningModal')).show();
    $.ajax({
        url: '{{ route("claimant.running.km") }}', method: 'GET',
        data: { ei: empid, YearId: yearid, CompanyId: comid, month },
        success(res) {
            const items = res.data?.items   || [];
            const act   = res.data?.activity|| [];
            $('#runTotalKm').text((res.data?.monthly_total_km||0)+' KM');
            let rows = items.length ? '' : '<tr><td colspan="5" class="text-center text-muted">No entries.</td></tr>';
            items.forEach(it => {
                rows += `<tr>
                    <td>${fd(it.BillDate)}</td>
                    <td>${it.odomtr_opening}</td>
                    <td>${it.odomtr_closing}</td>
                    <td><strong>${parseFloat(it.TotKm||0)}</strong></td>
                    <td class="text-center">${it.c_verify==='N'?'<span class="sbg sbg-pending">Pending</span>':'<span class="sbg sbg-verified">Verified</span>'}</td>
                </tr>`;
            });
            $('#runTableBody').html(rows);
            let arows = act.length ? '' : '<tr><td colspan="3" class="text-center text-muted">No activity.</td></tr>';
            act.forEach(a => {
                arows += `<tr><td>${a.action||'—'}</td><td>${a.remark||'—'}</td><td>${fd(a.date_submitted)}</td></tr>`;
            });
            $('#runActivityBody').html(arows);
        },
        error() { $('#runTableBody').html('<tr><td colspan="5" class="text-danger text-center">Failed.</td></tr>'); }
    });
});

/* ═══════════════ FINAL SUBMIT MONTH (Matches Flutter open_month.dart lines 2000-2175) ═══════════════ */
$(document).on('click', '.submit-month-btn', function(e) {
    e.stopPropagation();
    const month = $(this).data('month');
    const empid = $(this).data('empid') || MCV.ei;
    const yearid = $(this).data('yearid') || MCV.yr;
    const comid = $(this).data('comid') || MCV.co;
    const totClaim = $(this).data('totclaim') || 0;
    const totFilledAmt = $(this).data('totfilledamt') || 0;
    const totVerifiedClaims = $(this).data('totverifiedclaims') || 0;
    const totVerifiedAmt = $(this).data('totverifiedamt') || 0;
    const totDeniedClaims = $(this).data('totdeniedclaims') || 0;

    const mn = MNF[parseInt(month)] || ('Month ' + month);

    Swal.fire({
        title: `Submit Claims — ${mn}`,
        html: `
            <div class="text-start mt-2 p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:0.84rem;">
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Month:</span>
                    <strong class="text-primary">${mn}</strong>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Total Claims:</span>
                    <strong>${totClaim}</strong>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Filled Amount:</span>
                    <strong class="text-primary">${rp(totFilledAmt)}</strong>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Verified Claims:</span>
                    <strong class="text-success">${totVerifiedClaims}</strong>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Verified Amount:</span>
                    <strong class="text-success">${rp(totVerifiedAmt)}</strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Denied Claims:</span>
                    <strong class="text-danger">${totDeniedClaims}</strong>
                </div>
            </div>
            <p class="mt-3 text-muted small mb-0">Are you sure you want to final submit this month for approval?</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="ri-checkbox-circle-line me-1"></i> Submit Month',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Submitting Month…',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            $.ajax({
                url: '{{ route("claimant.month.submit") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    month: month,
                    empid: empid,
                    YearId: yearid,
                    CompanyId: comid
                },
                success(res) {
                    if (res.Code === '200' || (res.msg||'').toLowerCase().includes('success') || res.msg === "Month claim submitted successfully") {
                        Swal.fire({
                            icon: 'success',
                            title: 'Submitted Successfully!',
                            text: res.msg || 'Month claim submitted successfully',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        loadOpen();
                        loadClosed();
                    } else {
                        Swal.fire('Error', res.msg || 'Failed to submit month.', 'error');
                    }
                },
                error(err) {
                    Swal.fire('Error', err.responseJSON?.msg || 'Server error occurred.', 'error');
                }
            });
        }
    });
});

/* ═══════════════ COURIER / DISPATCH DETAILS (Matches Flutter close_month.dart) ═══════════════ */
$(document).on('click', '.courier-btn', function(e) {
    e.stopPropagation();
    const empid = $(this).data('empid') || MCV.ei;
    const month = $(this).data('month');
    const yearid = $(this).data('yearid') || MCV.yr;
    const comid = $(this).data('comid') || MCV.co;
    const postDate = $(this).data('postdate');
    const docateNo = $(this).data('docateno');
    const agency = $(this).data('agency');
    const recevingDate = $(this).data('recevingdate');
    const docRmk = $(this).data('docrmk');
    const cuImag = $(this).data('cuimag');

    const mn = MNF[parseInt(month)] || ('Month ' + month);
    $('#courierModalTitle').text(`Courier Details — ${mn}`);

    const hasCourierSend = postDate && postDate !== '0000-00-00';

    if (hasCourierSend) {
        // Submitted Courier Tracking Details View (mirrors Flutter close_month.dart lines 2600-2680)
        let podHtml = '';
        if (cuImag) {
            if (isPdfUrl(cuImag)) {
                podHtml = `
                <div class="mt-3">
                    <label class="fw-bold small text-muted d-block mb-1">COURIER POD / RECEIPT</label>
                    <a href="${cuImag}" target="_blank" class="mcv-pdf-chip">
                        <i class="ri-file-pdf-2-fill"></i> View Courier Receipt PDF
                    </a>
                </div>`;
            } else {
                podHtml = `
                <div class="mt-3">
                    <label class="fw-bold small text-muted d-block mb-1">COURIER POD / RECEIPT</label>
                    <div class="dm-file-chip zoom-img" data-img="${cuImag}" title="Click to enlarge" style="width:100px;height:100px;">
                        <img src="${cuImag}" alt="Courier Receipt">
                    </div>
                </div>`;
            }
        }

        const isDelivered = recevingDate && recevingDate !== '0000-00-00';

        $('#courierBody').html(`
            <div class="p-3 mb-3 rounded-3" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                <div class="d-flex align-items-center gap-2 text-success fw-bold" style="font-size:0.88rem;">
                    <i class="ri-checkbox-circle-fill fs-5"></i> Hardcopy Dispatched
                </div>
                <div class="text-muted mt-1" style="font-size:0.75rem;">
                    Your physical bill package has been marked as dispatched for verification.
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered mb-0" style="font-size:0.84rem;">
                    <tbody>
                        <tr>
                            <td class="bg-light fw-bold" style="width:40%;">Dispatch / Post Date:</td>
                            <td class="fw-semibold text-primary">${fd(postDate)}</td>
                        </tr>
                        <tr>
                            <td class="bg-light fw-bold">Docket / Waybill No:</td>
                            <td class="fw-bold" style="color:#1e293b;">${docateNo || '—'}</td>
                        </tr>
                        <tr>
                            <td class="bg-light fw-bold">Courier Agency:</td>
                            <td class="fw-semibold">${agency || '—'}</td>
                        </tr>
                        <tr>
                            <td class="bg-light fw-bold">Receiving Status:</td>
                            <td>
                                ${isDelivered
                                    ? `<span class="badge bg-success"><i class="ri-check-line me-1"></i>Received on ${fd(recevingDate)}</span>`
                                    : `<span class="badge bg-warning text-dark"><i class="ri-time-line me-1"></i>In Transit / Pending Delivery</span>`}
                            </td>
                        </tr>
                        ${(docRmk && docRmk !== '—') ? `
                        <tr>
                            <td class="bg-light fw-bold">Receiving Remarks:</td>
                            <td class="text-muted">${docRmk}</td>
                        </tr>` : ''}
                    </tbody>
                </table>
            </div>
            ${podHtml}
            <div class="text-end mt-4">
                <button type="button" class="btn btn-sm btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        `);
    } else {
        // Courier Dispatch Submission Form (mirrors Flutter close_month.dart lines 2480-2560)
        const today = new Date().toISOString().split('T')[0];
        $('#courierBody').html(`
            <form id="courierSubmitForm" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="empid" value="${empid}">
                <input type="hidden" name="month" value="${month}">
                <input type="hidden" name="YearId" value="${yearid}">
                <input type="hidden" name="ComId" value="${comid}">

                <div class="p-3 mb-3 rounded-3" style="background:#fffbeb;border:1px solid #fde68a;">
                    <div class="d-flex align-items-center gap-2 text-warning-emphasis fw-bold" style="font-size:0.84rem;">
                        <i class="ri-information-line fs-5 text-warning"></i> Send Hardcopy Bills
                    </div>
                    <div class="text-muted mt-1" style="font-size:0.75rem;">
                        Please dispatch your physical bills and enter the courier tracking details below.
                    </div>
                </div>

                <div class="mcv-fg mb-3">
                    <label class="fw-bold small mb-1">Post Date / Dispatch Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-sm" name="PostDate" id="cPostDate" value="${today}" max="${today}" required>
                </div>
                <div class="mcv-fg mb-3">
                    <label class="fw-bold small mb-1">Docket Number / Waybill Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="DocateNo" id="cDocket" placeholder="Enter tracking/docket number…" aria-label="Enter tracking/docket number…" required>
                </div>
                <div class="mcv-fg mb-3">
                    <label class="fw-bold small mb-1">Courier Agency Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="Agency" id="cAgency" placeholder="e.g. Blue Dart, DTDC, Professional, Speed Post…" aria-label="e.g. Blue Dart, DTDC, Professional, Speed Post…" required>
                </div>
                <div class="mcv-fg mb-3">
                    <label class="fw-bold small mb-1">Upload Courier Receipt / POD Photo <small class="text-muted">(Optional)</small></label>
                    <input type="file" class="form-control form-control-sm" name="file" id="cFile" accept="image/*,.pdf">
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4" id="btnSubmitCourier">
                        <i class="ri-send-plane-fill me-1"></i> Submit Details
                    </button>
                </div>
            </form>
        `);
    }

    new bootstrap.Modal(document.getElementById('courierModal')).show();
});

// Courier Form Submission AJAX Handler
$(document).on('submit', '#courierSubmitForm', function(e) {
    e.preventDefault();
    const form = $(this)[0];
    const postDate = $('#cPostDate').val();
    const docket = $('#cDocket').val().trim();
    const agency = $('#cAgency').val().trim();

    if (!postDate || !docket || !agency) {
        Swal.fire('Required Fields', 'Please fill in Post Date, Docket Number, and Courier Agency.', 'warning');
        return;
    }

    const btn = $('#btnSubmitCourier');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Submitting…');

    const formData = new FormData(form);

    $.ajax({
        url: '{{ route("claimant.courier.submit") }}',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success(res) {
            btn.prop('disabled', false).html('<i class="ri-send-plane-fill me-1"></i> Submit Details');
            if (res.Code === '200' || res.msg === 'Data Updated successfully' || (res.msg||'').toLowerCase().includes('success')) {
                Swal.fire({
                    icon: 'success',
                    title: 'Submitted Successfully!',
                    text: 'Your courier tracking details have been registered.',
                    timer: 1800,
                    showConfirmButton: false
                });
                const modalEl = document.getElementById('courierModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                // Reload closed month list to reflect the updated courier status immediately
                loadClosed($('#closedMonthFilter').val());
            } else {
                Swal.fire('Error', res.msg || 'Failed to submit courier details.', 'error');
            }
        },
        error(err) {
            btn.prop('disabled', false).html('<i class="ri-send-plane-fill me-1"></i> Submit Details');
            Swal.fire('Error', err.responseJSON?.msg || 'Server error while submitting courier details.', 'error');
        }
    });
});

/* ═══════════════ ACCORDION TOGGLE HANDLER ═══════════════ */
$(document).on('click', '.month-tile-header', function(e) {
    // If the click was on any button, link, input, or inside .mcv-month-right, ignore accordion toggle
    if ($(e.target).closest('button, a, input, select, .mcv-month-right, .mcv-ab').length) {
        return;
    }
    const targetId = $(this).attr('data-target-collapse');
    if (targetId) {
        const collapseEl = document.getElementById(targetId);
        if (collapseEl) {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl);
            bsCollapse.toggle();
            const isOpen = collapseEl.classList.contains('show');
            $(this).attr('aria-expanded', !isOpen);
        }
    }
});

/* ═══════════════ LIGHTBOX / FULLSCREEN IMAGE ═══════════════ */
function openLightbox(src) {
    if (!src || src.includes('pdf-icon.png')) return;
    $('#mcvLightboxImg').attr('src', src);
    $('#mcvLightbox').addClass('active');
    $('body').css('overflow', 'hidden');
}
window.openLightbox = openLightbox;

function closeLightbox() {
    $('#mcvLightbox').removeClass('active');
    $('body').css('overflow', '');
}
window.closeLightbox = closeLightbox;

$(document).on('click', '.zoom-img, .cc-img-wrap img, .draft-img-wrap img, .draft-extra-thumb, .mcv-tbl-thumb, .dm-file-chip', function(e) {
    e.stopPropagation();
    const src = $(this).attr('data-img') || $(this).attr('src') || $(this).find('img').attr('src');
    if (src && !src.includes('pdf-icon.png')) {
        openLightbox(src);
    }
});

$(document).on('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});

function openPop(url) {
    window.open(url,'_blank','toolbar=no,scrollbars=yes,resizable=yes,width=920,height=540,top=80,left=200');
}
function emptyState(id, icon, msg) {
    $(`#${id}`).html(`<div class="mcv-empty"><div class="mcv-empty-icon"><i class="${icon}"></i></div><p>${msg}</p></div>`);
}
function errorState(id) {
    $(`#${id}`).html(`<div class="mcv-empty"><div class="mcv-empty-icon" style="background:#fee2e2;"><i class="ri-error-warning-line" style="color:#dc2626;"></i></div><p class="text-danger mb-0">Failed to load. <a href="#" onclick="location.reload()">Retry</a></p></div>`);
}

$('#mcvRefreshBtn').on('click', function() {
    loadDrafts();
    loadOpen();
    loadClosed($('#closedMonthFilter').val());
    $(this).find('i').addClass('spin-me');
    setTimeout(() => $(this).find('i').removeClass('spin-me'), 900);
});

/* ═══════════════ INIT ═══════════════ */
$(function() {
    loadDrafts();
    loadOpen();
    loadClosed();
});
</script>
@endpush
