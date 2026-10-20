/* global wp, PUE_Editor */
(function() {
  if (!window.wp) return;
  var plugins = wp.plugins, editPost = wp.editPost, components = wp.components, element = wp.element, data = wp.data, coreData = wp.coreData;
  if (!plugins || !editPost || !components || !element || !data || !coreData) {
    return; // Required packages not present (older WordPress or Classic editor).
  }
  var settings = window.PUE_Editor || {};
  if (settings.enabled === false || settings.enabled === '') {
    return; // The flag is registered for public post types only.
  }
  var __ = (wp.i18n && wp.i18n.__) ? wp.i18n.__ : function(s){ return s; };
  var el = element.createElement;

  var DoNotNotify = function DoNotNotify() {
    var select = data.select('core/editor');
    var postType = select && select.getCurrentPostType ? select.getCurrentPostType() : 'post';
    var ref = coreData.useEntityProp('postType', postType, 'meta');
    var meta = ref[0], setMeta = ref[1];
    return el(editPost.PluginPostStatusInfo, null,
      el('div', null,
        el(components.CheckboxControl, {
          label: __('Don’t send email notifications', 'hackrepair-post-update-email-notifier'),
          checked: !!(meta && meta._pue_do_not_notify),
          onChange: function(next) { setMeta(Object.assign({}, meta, { _pue_do_not_notify: !!next })); }
        }),
        settings.note ? el('p', { className: 'components-base-control__help', style: { marginTop: '4px' } }, settings.note) : null
      )
    );
  };

  plugins.registerPlugin('pue-do-not-notify', { render: DoNotNotify, icon: null });
})();
