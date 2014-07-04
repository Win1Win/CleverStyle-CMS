// Generated by CoffeeScript 1.4.0

/**
 * @package		UI automatic helpers
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2014, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
*/


(function() {

  $(function() {
    var ui_automatic_helpers_update;
    ui_automatic_helpers_update = function(element) {
      element.filter('.cs-table').addClass('uk-table uk-table-condensed uk-table-hover');
      element.find('.cs-table').addClass('uk-table uk-table-condensed uk-table-hover');
      element.find('.SIMPLEST_INLINE_EDITOR').prop('contenteditable', true);
      element.filter('[data-title]:not(data-uk-tooltip)').cs().tooltip();
      element.find('[data-title]:not(data-uk-tooltip)').cs().tooltip();
      element.filter('.cs-tabs:not(.uk-tab)').cs().tabs();
      element.find('.cs-tabs:not(.uk-tab)').cs().tabs();
      if (element.is('.cs-no-ui') || element.parents().filter('.cs-no-ui').length) {
        return;
      }
      element.filter('form:not(.uk-form)').addClass('uk-form');
      element.find('form:not(.cs-no-ui, .uk-form)').addClass('uk-form');
      element.filter(':not(.uk-button) > input:radio:not(.cs-no-ui)').cs().radio();
      element.find(':not(.uk-button) > input:radio:not(.cs-no-ui)').cs().radio();
      element.filter(':not(.uk-button) > input:checkbox:not(.cs-no-ui)').cs().checkbox();
      element.find(':not(.uk-button) > input:checkbox:not(.cs-no-ui)').cs().checkbox();
      element.filter(':button:not(.uk-button), .cs-button, .cs-button-compact').addClass('uk-button').disableSelection();
      element.find(':button:not(.cs-no-ui, .uk-button), .cs-button, .cs-button-compact').addClass('uk-button').disableSelection();
      element.filter('textarea:not(.cs-no-resize, .EDITOR, .SIMPLE_EDITOR, .cs-autosized)').addClass('cs-autosized').autosize({
        append: "\n"
      });
      return element.find('textarea:not(.cs-no-ui, .cs-no-resize, .EDITOR, .SIMPLE_EDITOR, .cs-autosized)').addClass('cs-autosized').autosize({
        append: "\n"
      });
    };
    ui_automatic_helpers_update($('body'));
    return (function() {
      var MutationObserver, eventListenerSupported;
      MutationObserver = window.MutationObserver || window.WebKitMutationObserver;
      eventListenerSupported = window.addEventListener;
      if (!MutationObserver) {
        return (new MutationObserver(function(mutations) {
          return mutations.forEach(function() {
            if (this.addedNodes.length) {
              return ui_automatic_helpers_update($(this));
            }
          });
        })).observe(document.body, {
          childList: true,
          subtree: true
        });
      } else if (eventListenerSupported) {
        return document.body.addEventListener('DOMNodeInserted', function() {
          return ui_automatic_helpers_update($('body'));
        }, false);
      }
    })();
  });

}).call(this);