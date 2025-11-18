/* global wp */
(function() {
  if (!window.wp) return;
  var plugins = wp.plugins;
  var editPost = wp.editPost;
  var components = wp.components;
  var element = wp.element;
  var data = wp.data;
  var coreData = wp.coreData;

  if (!plugins || !editPost || !components || !element || !data || !coreData) {
    return; // required packages not present (older WP or classic editor view)
  }

  var PluginPostStatusInfo = editPost.PluginPostStatusInfo;
  var CheckboxControl = components.CheckboxControl;
  var createElement = element.createElement;

  var DoNotNotify = function DoNotNotify() {
    var select = data.select('core/editor');
    var postType = select && select.getCurrentPostType ? select.getCurrentPostType() : 'post';
    // Read/write the entire meta object
    var useEntityProp = coreData.useEntityProp;
    var ref = useEntityProp('postType', postType, 'meta');
    var meta = ref[0];
    var setMeta = ref[1];
    var enabled = !!(meta && meta._pue_do_not_notify);

    return createElement(PluginPostStatusInfo, null,
      createElement(CheckboxControl, {
        label: 'Don’t send email notifications',
        checked: enabled,
        onChange: function(next) {
          var nextMeta = Object.assign({}, meta, { _pue_do_not_notify: !!next });
          setMeta(nextMeta);
        }
      })
    );
  };

  plugins.registerPlugin('pue-do-not-notify', {
    render: DoNotNotify,
    icon: null
  });
})();
