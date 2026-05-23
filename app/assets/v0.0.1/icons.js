const PATHS = {

    home:        '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>',
    user:        '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    users:       '<circle cx="9" cy="8" r="4"/><path d="M3 21a6 6 0 0 1 12 0"/><circle cx="17" cy="8" r="3"/><path d="M14 21a5 5 0 0 1 8 0"/>',
    settings:    '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.01A1.65 1.65 0 0 0 9 4.6V4.5a2 2 0 0 1 4 0v.09c0 .67.39 1.27 1 1.51.61.24 1.32.11 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06c-.46.46-.6 1.15-.33 1.82V11c.24.61.84 1 1.51 1H21a2 2 0 0 1 0 4h-.09c-.67 0-1.27.39-1.51 1z"/>',
    arrowLeft:   '<path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/>',
    arrowRight:  '<path d="M5 12h14"/><path d="M12 5l7 7-7 7"/>',
    chevronLeft: '<path d="M15 18l-6-6 6-6"/>',
    chevronRight:'<path d="M9 18l6-6-6-6"/>',
    chevronUp:   '<path d="M18 15l-6-6-6 6"/>',
    chevronDown: '<path d="M6 9l6 6 6-6"/>',
    close:       '<path d="M18 6L6 18"/><path d="M6 6l12 12"/>',
    check:       '<path d="M20 6L9 17l-5-5"/>',
    refresh:     '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/>',
    globe:       '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>',


    wallet:      '<path d="M3 6h15a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6z"/><path d="M3 6V5a2 2 0 0 1 2-2h13"/><circle cx="17" cy="13.5" r="1.2" fill="currentColor"/>',
    coin:        '<circle cx="12" cy="12" r="9"/><path d="M14.5 9.5a3 3 0 0 0-5 0M9.5 14.5a3 3 0 0 0 5 0M12 7v2M12 15v2"/>',
    cart:        '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2l2.7 11.5a2 2 0 0 0 2 1.5h7.5a2 2 0 0 0 2-1.5L21 8H6"/>',
    creditCard:  '<rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20"/><path d="M6 15h4"/>',
    diamond:     '<path d="M2.7 10.3l9.3 11 9.3-11L17 4H7z"/><path d="M2.7 10.3h18.6"/><path d="M7 4l5 6.3L17 4"/>',
    star:        '<path d="M12 2l3 7 7 .8-5.3 4.8 1.6 7.4L12 18l-6.3 4 1.6-7.4L2 9.8 9 9z"/>',
    bitcoin:     '<circle cx="12" cy="12" r="9"/><path d="M9 7v10M14 7v10M9 9h5a2 2 0 0 1 0 4H9"/><path d="M9 13h6a2 2 0 0 1 0 4H9"/>',
    flower:      '<circle cx="12" cy="12" r="2"/><path d="M12 4a4 4 0 0 1 4 4M12 4a4 4 0 0 0-4 4M20 12a4 4 0 0 1-4 4M20 12a4 4 0 0 0-4-4M12 20a4 4 0 0 1-4-4M12 20a4 4 0 0 0 4-4M4 12a4 4 0 0 1 4-4M4 12a4 4 0 0 0 4 4"/>',


    rotate:      '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/>',
    clock:       '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    hourglass:   '<path d="M6 3h12M6 21h12M7 3v3a5 5 0 0 0 10 0V3M7 21v-3a5 5 0 0 1 10 0v3"/>',
    box:         '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/>',
    link:        '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
    power:       '<path d="M18.4 6.6a9 9 0 1 1-12.7 0"/><path d="M12 2v10"/>',
    powerOff:    '<path d="M18.4 6.6a9 9 0 1 1-12.7 0"/><path d="M12 2v10"/><path d="M5 5l14 14" stroke-opacity="0.5"/>',
    send:        '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>',
    transfer:    '<path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
    pin:         '<path d="M12 22s8-7.5 8-13a8 8 0 1 0-16 0c0 5.5 8 13 8 13z"/><circle cx="12" cy="9" r="3"/>',
    alert:       '<path d="M10.3 3.86l-8.5 14.14a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3l-8.5-14.14a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><circle cx="12" cy="17" r="0.8" fill="currentColor"/>',
    note:        '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h6"/>',
    fileText:    '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h6M9 9h2"/>',
    book:        '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
    config:      '<path d="M12 2L4 6v6c0 5 3.5 9 8 10 4.5-1 8-5 8-10V6z"/>',
    bulb:        '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M9 14a5 5 0 1 1 6 0c-1 .8-1.5 1.5-1.5 3h-3c0-1.5-.5-2.2-1.5-3z"/>',
    download:    '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
    copy:        '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    crown:       '<path d="M3 7l4 6 5-9 5 9 4-6 1 13H2z"/>',
    calendar:    '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/>',
    phone:       '<path d="M21 16.5v3a2 2 0 0 1-2 2 18 18 0 0 1-16-16 2 2 0 0 1 2-2h3a1 1 0 0 1 1 .8c.1 1 .3 2 .6 3a1 1 0 0 1-.3 1L7.7 9.3a16 16 0 0 0 7 7l2-2a1 1 0 0 1 1-.3c1 .3 2 .5 3 .6a1 1 0 0 1 .8 1z"/>',
    chart:       '<path d="M3 3v18h18"/><path d="M7 14l3-3 4 4 5-6"/>',


    info:        '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8v.01" stroke-linecap="round"/>',
    warning:     '<path d="M10.3 3.86l-8.5 14.14a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3l-8.5-14.14a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17v.01"/>',
    checkCircle: '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
    xCircle:     '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>',
    qrCode:      '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M14 19v2M19 14h2M17 17h4v4"/>',
    camera:      '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
    graduationCap: '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/>',
    online:      '<circle cx="12" cy="12" r="3" fill="currentColor"/><circle cx="12" cy="12" r="9" stroke-opacity="0.4"/>',
    plus:        '<path d="M12 5v14M5 12h14"/>',
    minus:       '<path d="M5 12h14"/>',


    tron:        '<circle cx="12" cy="12" r="9"/><path d="M7 8l9 1.5-4 9z" fill="currentColor" stroke="none"/><path d="M7 8l5 11M7 8l9 1.5M12 19l4-9.5"/>',
    ton:         '<circle cx="12" cy="12" r="9"/><path d="M7 8h10l-5 10z" fill="currentColor" stroke="none"/><path d="M7 8h10M12 18l-5-10M12 18l5-10"/>',
    tether:      '<circle cx="12" cy="12" r="9"/><path d="M7 8h10M12 8v10" /><path d="M9.5 8h5v3h-5z" fill="currentColor" stroke="none"/>',
    flag:        '<path d="M4 22V4"/><path d="M4 4h14l-2 5 2 5H4"/>',
    earth:       '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>',
    walletOut:   '<path d="M3 7h15a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3z"/><path d="M3 7V6a3 3 0 0 1 3-3h11"/><path d="M14 14h4M16 12l2 2-2 2"/>',
    exchange:    '<path d="M3 8h14l-3-3M3 8l3 3"/><path d="M21 16H7l3 3M21 16l-3-3"/>',
};


export function icon(name, attrs = '') {
    const body = PATHS[name];

    const hasClass = /(^|\s)class\s*=/.test(attrs);
    const defaultClass = hasClass ? '' : ' class="ico"';
    if (!body) {


        return `<svg viewBox="0 0 24 24"${defaultClass} ${attrs}><circle cx="12" cy="12" r="9" stroke-dasharray="3 3"/></svg>`;
    }
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"${defaultClass} ${attrs}>${body}</svg>`;
}


export function icons(...names) {
    return names.map((n) => icon(n)).join('');
}

