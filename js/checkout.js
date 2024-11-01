const settings = window.wc.wcSettings.getSetting('layup_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || 'layup';
const Content = () => {
    return window.wp.htmlEntities.decodeEntities(settings.description || '');
};
const Block_Gateway = {
    name: 'layup',
    label: label,
    content: Object(window.wp.element.createElement) (Content, null),
    edit: Object(window.wp.element.createElement) (Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);