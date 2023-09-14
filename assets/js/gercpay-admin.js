const gpbSectionBtnSettings = document.querySelector('#gpb_section_btn_settings');
const gpbButtonPreview = document.querySelector('#btn_preview');
const gpbButtonPreview_text = document.querySelector('#btn_preview_text');
const gpbPreview = [gpbButtonPreview, gpbButtonPreview_text];
const gpbButtonSettings = ['btn_shape', 'btn_height', 'btn_width', 'btn_color', 'btn_border', 'btn_inverse'];
if (typeof gpbSectionBtnSettings != 'undefined' && gpbSectionBtnSettings) {
  gpbSectionBtnSettings.addEventListener('change', function(e) {
    let el = e.target;
    if (gpbButtonSettings.includes(el.id)) {
      let newValue = el.value;
      let newClass = '';
      switch (el.id) {
        case 'btn_shape':
          newClass = `gpb-btn-shape-${newValue}`;
          gpbPreview.map(button => button.className = button.className.replace(/gpb-btn-shape[^\s]+/g, newClass));
          break;
        case 'btn_height':
          newClass = `gpb-btn-height-${newValue}`;
          gpbPreview.map(button => button.className = button.className.replace(/gpb-btn-height[^\s]+/g, newClass));
          break;
        case 'btn_width':
          gpbPreview.map(button => button.style.width = `${newValue}px`);
          break;
        case 'btn_color':
          let color = '#FFFFFF';
          if (el.value === 'gold') {
            color = '#FFC439';
          } else if (el.value === 'blue') {
            color = '#0170BA';
          } else if (el.value === 'silver') {
            color = '#EEEEEE';
          } else if (el.value === 'white') {
            color = '#FFFFFF';
          } else if (el.value === 'black') {
            color = '#2C2E2F';
          }
          gpbPreview.map(button => button.style.backgroundColor = color);
          break;
        case 'btn_border':
          newClass = `gpb-btn-border-${newValue}`;
          gpbPreview.map(button => button.className = button.className.replace(/gpb-btn-border[^\s]+/g, newClass));
          break;
        case 'btn_inverse':
          gpbPreview.map(button => toggleBtnImage(el.value, button));
          break;
      }
    }
  });
}

function toggleBtnImage(type, btn) {
  let isNormal = btn.style.backgroundImage.includes('gercpay.svg');
  if (isNormal) {
    btn.style.backgroundImage = btn.style.backgroundImage.replace('gercpay.svg', 'gercpay-inverse.svg');
  } else {
    btn.style.backgroundImage = btn.style.backgroundImage.replace('gercpay-inverse.svg', 'gercpay.svg');
  }
}