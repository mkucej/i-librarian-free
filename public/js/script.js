/*
 * I, Librarian.
 */

'use strict';

jQuery.fn.extend({
    insertAtCaret: function (myValue) {
        return this.each(function (i) {
            if (document.selection) {
                //For browsers like Internet Explorer
                this.focus();
                let sel = document.selection.createRange();
                sel.text = myValue;
                this.focus();
            } else if (this.selectionStart || this.selectionStart === 0) {
                //For browsers like Firefox and Webkit based
                let startPos = this.selectionStart;
                let endPos = this.selectionEnd;
                let scrollTop = this.scrollTop;
                this.value = this.value.substring(0, startPos) + myValue + this.value.substring(endPos, this.value.length);
                this.focus();
                this.selectionStart = startPos + myValue.length;
                this.selectionEnd = startPos + myValue.length;
                this.scrollTop = scrollTop;
            } else {
                this.value += myValue;
                this.focus();
            }
        });
    }
});

// IL namespace.
window.IL = [];
window.charts = [];
window.tables = [];
// Lodash.
let _ = window._;
// Backbone.
let B = window.Backbone;

/*
 * Widgets.
 */

/**
 * Typeahead widget. AJAX-powered combobox.
 *
 * @requires jQuery
 * @requires jQuery UI Widget
 * @requires Popper
 * @requires bootstrap
 * @requires model object
 */
$.widget("il.typeahead", {
    searchId:    '',
    ignoredKeys: [9, 13, 16, 17, 18, 20, 27, 33, 34, 35, 36, 37, 38, 39, 40, 45],
    options: {
        delay:     300,
        minLength: 0,
        source:    ''
    },
    _getCreateOptions: function() {
        return {
            delay:     this.element.attr('data-delay'),
            minLength: this.element.attr('data-minLength'),
            source:    this.element.attr('data-source')
        };
    },
    /**
     * Constructor.
     */
    _create: function() {
        // Input keyup.
        this._on(this.element, {input: this._search, focus: this._search});
        // Select dropdown option.
        let $menu = this.element.parent().next();
        this._on($menu, {'click button': function (e) {
            this.element.val($(e.target).text()).attr('data-id', $(e.target).attr('data-id'));
            this._clearMenus();
            this._trigger("onSelect", null, {element: this.element});
        }});
        // Menu focus management using arrows.
        this._on($menu, {'keyup button': function (e) {
            e.stopPropagation();
            if (e.which === 38) {
                $(e.target).prev().focus();
            }
            if (e.which === 40) {
                $(e.target).next().focus();
            }
            // Escape.
            if (e.which === 27) {
                this.element.focus();
                this._clearMenus();
            }
        }});
        // Outside click closes dropdown.
        this._on('body', {'click': this._clearMenus});
        // Stop inside click propagation to trigger menu close.
        this._on(this.element.closest('.typeahead'), {click: function (e) {
            e.stopPropagation();
        }});
        // Resize dropdown on window resize.
        this._on(this.window, {resize: this._resizeMenus});
    },
    /**
     * Search controller.
     * @param   {object} e Input.
     * @returns {Boolean}
     */
    _search: function (e) {
        // Tab and down-arrow jumps to the menu.
        if (e.which === 9 || e.which === 40) {
            $('.typeahead').find('button:first').focus();
            return false;
        }
        // Escape.
        if (e.which === 27) {
            this._clearMenus();
        }
        // Min query length.
        if (this.element.val().length < this.options.minLength) {
            this._clearMenus();
            return false;
        }
        // Search. Ignore some keys.
        if (this.ignoredKeys.indexOf(e.which) !== -1) {
            return false;
        }
        clearTimeout(this.searchId);
        this.searchId = this._delay(this._load, this.options.delay);
    },
    /**
     * Remote model.
     */
    _load: function () {
        let typeahead = this, $t = this.element, $menu = $t.parent().next();
        $.when(model.load({
            url: this.options.source,
            data: {q: this.element.val()}
        })).done(function (response) {
            // No results.
            if (response.html === '') {
                typeahead._clearMenus();
                return;
            }
            // Open menu.
            $menu.width($t.outerWidth() - 2).show().prev().attr('aria-expanded', true);
            let popper = new Popper($t[0], $menu[0], {
                placement: 'bottom',
                eventsEnabled: true,
                positionFixed: true,
                modifiers: {
                    preventOverflow: {
                        enabled: false
                    },
                    hide: {
                        enabled: false
                    }
                }
            });
            // Populate menu.
            $menu.html(response.html);
            popper.update();
        });
    },
    /**
     * Clear dropdown menu.
     */
    _clearMenus: function () {
        $('.typeahead .dropdown-menu').hide().empty().prev().attr('aria-expanded', false);
    },
    /**
     * Resize dropdown menu.
     */
    _resizeMenus: function () {
        $('.typeahead .dropdown-menu').each(function () {
            $(this).width($(this).parent().outerWidth() - 2);
        });
    }
});

/**
 * Filter widget. Local or remote search.
 * @requires jQuery
 * @requires jQuery UI Widget
 * @requires model object
 */
$.widget("il.filterable", {
    event:     '',
    searchStr: '',      // Last search string.
    searchId:  '',      // Search time ID.
    options: {
        container: '',  // Container ID to replace with the remote search results.
        minLength:  1,  // Min. search string length.
        source:    '',  // Remote URL to get search results.
        targets:   ''   // Elements to filter using local search.
    },
    _getCreateOptions: function() {
        return {
            container: this.element.attr('data-container'),
            minLength: this.element.attr('data-minLength'),
            source:    this.element.attr('data-source'),
            targets:   this.element.attr('data-targets')
        };
    },
    /**
     * Constructor.
     */
    _create: function() {
        // Bind keyup to the input element.
        this._on(this.element, {'input': $.proxy(this, '_filter')});
        this._addClass('filterable');
    },
    /**
     * Filter either using local regexp, or remote search.
     * @returns {Boolean}
     */
    _filter: function(e) {
        this.event = e;
        // Search string must differ from the last.
        if (this.element.val() === this.searchStr) {
            return false;
        }
        this.searchStr = this.element.val();
        // Min. length.
        if (this.searchStr.replace('=', '').length < this.options.minLength) {
            this.searchStr = '';
        }
        clearTimeout(this.searchId);
        if (this.options.source !== '' && this.options.container !== '') {
            // Remote search.
            this.searchId = this._delay(this._remoteSearch, 400);
        } else if (this.options.targets !== '') {
            // Local search.
            this.searchId = this._delay(this._localSearch, 200);
        }
    },
    /**
     * Filter local elements.
     */
    _localSearch: function () {
        let escStr = this._escapeRegexp(this.searchStr),
            patt = new RegExp(escStr, 'ui'),
            patt2 = new RegExp('\(' + escStr + '\)', 'gui');
        // Begins with.
        if (escStr.indexOf('=') === 0) {
            patt = new RegExp('\^' + escStr.substring(1), 'ui');
            patt2 = new RegExp('\^\(' + escStr.substring(1) + '\)', 'gui');
        }
        if (this.searchStr.length >= this.options.minLength) {
            $(this.options.targets).addClass('d-none');
            $(this.options.targets).filter(function () {
                let $t = $(this), txt = $t.text(), b = patt.test(txt);
                if (b === true) {
                    $t.html(txt.replace(patt2, '<mark class="px-0">$1</mark>'));
                }
                return b;
            }).removeClass('d-none');
        } else {
            $(this.options.targets).removeClass('d-none').each(function () {
                let $t = $(this);
                $t.html($t.text());
            });
        }
        this._trigger('complete', this.event);
    },
    /**
     * Search remote source.
     */
    _remoteSearch: function () {
        let This = this, href = this.options.source,
            cont = this.options.container;
        $.when(model.load({
            url: href,
            data: {q: This.searchStr}
        })).done(function (response) {
            // Replace container.
            $(cont).replaceWith(response.html);
            This._trigger('complete', This.event);
        });
    },
    /**
     * Escape string for Regexp use.
     * @param   {string} str
     * @returns {string}
     */
    _escapeRegexp: function (str) {
        let specials = [
              "["
            , "]"
            , "/"
            , "{"
            , "}"
            , "("
            , ")"
            , "*"
            , "+"
            , "?"
            , "."
            , "\\"
            , "^"
            , "$"
            , "|"
        ];
        let patt = new RegExp('[\\' + specials.join('\\') + ']', 'gui');
        return str.replace(patt, "\\$&");
    }
});

/**
 * Expandable div. Expands from one line two full content div.
 * @requires jQuery
 * @requires jQuery UI Widget
 * @requires bootstrap
 * Also requires ../css/materialdesignicons.css icons.
 */
$.widget("il.expandable", {
    table: '<div class="il-expandable"></div>',
    cell1: '<div class="il-expandable-left"> \
                <button class="btn btn-sm btn-secondary" aria-controls=""> \
                    <span class="mdi mdi-chevron-right"></span> \
                </button> \
            </div>',
    cell2: '<div class="il-expandable-right" aria-expanded="true"></div>',
    /**
     * Constructor.
     */
    _create: function() {
        this._expandability();
        // User may resize to a smaller viewscreen width.
        this._on(this.window, {resize: this._expandability});
    },
    /**
     * Make the element expandable.
     */
    _expandability: function () {
        if (this.element.innerHeight() > 40 && this.element.find('.il-expandable-left').length === 0) {
            this.element.wrapInner(this.cell2).wrapInner(this.table);
            // Add button column.
            this.element.children().prepend(this.cell1);
            // Add id for ARIA purpose.
            let eid = 'il-expandable-' + this.uuid;
            this.element.find('.il-expandable-left')
                .find('button')
                .attr('aria-controls', eid);
            this.element.find('.il-expandable-right')
                .attr('id', eid)
                .attr('aria-expanded', 'false')
                .addClass('text-truncate');
            // Delegate button click.
            this._on(this.element, {'click .il-expandable-left > button': this._toggle});
        }
    },
    /**
     * Toggle container height.
     */
    _toggle: function () {
        // Toggle button icon.
        this.element.find('.il-expandable-left').find('.mdi')
            .toggleClass('mdi-chevron-right mdi-chevron-down');
        // Toggle div height.
        this.element.find('.il-expandable-right')
            .toggleClass('text-truncate')
            .attr('aria-expanded', function(i, attr){
                return attr === 'false' ? 'true' : 'false';
            });
    }
});

$.widget("il.clonable", {
    options: {
        target: ''    // Id of an element to copy.
    },
    /**
     * Constructor.
     */
    _create: function() {
        if (this.options.target === '') {
            return;
        }
        // Add target class.
        this._addClass($(this.options.target), 'clonable-target-' + this.uuid);
        // Delegate button click.
        this._on(this.element, {click: this._clone});
        this._on(this.element.next('.remove-clone-button'), {click: this._removeClone});
    },
    /**
     * Clone.
     */
    _clone: function () {
        let $target = $(this.options.target), parClass = 'clonable-target-' + this.uuid;
        // Clone the target, keep it in memory.
        let clonedEl = $target.clone();
        // Remove id from the old target. The cloned element will become a new target.
        $target.removeAttr('id');
        // Change all children 'id' and 'for' attributes.
        clonedEl.find('[id]').each(function () {
            let eid = $(this).attr('id'), uid = _.uniqueId();
            $(this).attr('id', eid + '-' + uid);
            // Find and change the corresponding for attribute.
            clonedEl.find('[for="' + eid + '"]').attr('for', eid + '-' + uid);
        });
        // Increment explicitly indexed keys in the name attributes.
        clonedEl.find('[name]').each(function () {
            let name = $(this).attr('name');
            let newName = name.replace(/\[(\d+)]/, function (match, number) {
                return '[' + (parseInt(number) + 1) + ']';
            });
            $(this).attr('name', newName);
        });
        // Reset text inputs to empty.
        clonedEl.find('[type="text"], [type="number"]').each(function () {
            this.value = '';
        });
        // Insert cloned element into DOM.
        clonedEl.insertAfter($('.' + parClass).last());
        // Update form styling.
        formStyle.init();
        this._trigger("onClone", null, {clonedTarget: clonedEl[0]});
    },
    _removeClone: function () {
        let parClass = 'clonable-target-' + this.uuid,
            $clones = $('.' + parClass),
            $last = $clones.last(),
            targetId = $last.attr('id');
        if ($clones.length < 2) {
            return;
        }
        $last.remove();
        $('.' + parClass).last().attr('id', targetId);
    }
});

/**
 * Confirmable widget.
 */
$.widget("il.confirmable", {
    options: {
        target: '#modal-confirm',
        title:  'Confirmation',
        body:   'Confirm?',
        button: 'Yes',
        submit: function () {}
    },
    _getCreateOptions: function() {
        return {
            target: this.element.data('target'),
            title:  this.element.data('title'),
            body:   this.element.data('body'),
            button: this.element.data('button')
        };
    },
    _create: function() {
        // Delegate button click.
        this._on(this.element, {click: this._modal});
    },
    _modal: function () {
        let confirmable = this, $m = $(this.options.target), $btn = $m.find('.modal-footer > button').eq(0);
        // Modal HTML.
        $m.find('.modal-title').html(this.options.title);
        $m.find('.modal-body').html(this.options.body);
        $m.find('.modal-footer > button').eq(0).html(this.options.button);
        formStyle.init();
        $m.modal('show');
        // Bind submit event to modal button.
        $($btn).off('click').on('click',function (e) {
            confirmable._trigger('submit', e);
            $m.modal('hide');
        });
    }
});

/**
 * A widget to save form data to local storage for later use.
 *
 * After form render, start the widget like so:
 * $('#form-id').saveable();
 *
 * When form is submitted, save like so:
 * $('#form-id').saveable('save');
 *
 * @requires store
 * @requires formStyle
 */
$.widget("il.saveable", {
    _create: function() {
        // Only works on forms with an id.
        if (this.element.get(0).nodeName.toLowerCase() !== 'form' || this.element.attr('id') === undefined) {
            return;
        }
        this._on(this.element, {'save': this.save});
        // Load form on page render.
        this.load();
    },
    /**
     * Save the form data to local storage.
     */
    save: function () {
        let $f = this.element, saveParams = [], params = this.element.serializeArray();
        // Ignore hidden inputs.
        _.forEach(params, function (v, i) {
            if ($f.find('input[name="' + v.name + '"]').attr('type') !== 'hidden') {
                saveParams.push({
                    name: v.name,
                    value: v.value
                });
            }
        });
        store.save('saveable.' + this.element.attr('id'), saveParams);
    },
    /**
     * Load data from local storage and populate the form.
     */
    load: function () {
        let clonedNum = 0, This = this, formData = store.load('saveable.' + this.element.attr('id')) || [];
        this.element.get(0).reset();
        // Uncheck all checkboxes, because unchecked inputs are not serialized/saved.
        this.element.find(':checkbox').prop('checked', false);
        _.forEach(formData, function (o) {
            let input = This.element.find("[name='" + o.name + "']");
            if (input.length === 0 && clonedNum < 3) {
                This.element.find(".clone-button").trigger('click');
                clonedNum++;
                input = This.element.find("[name='" + o.name + "']");
            }
            input.val([o.value]);
        });
        formStyle.updateForm(this.element);
    },
    /**
     * Save external object as form data. Used by external search form loading.
     * @param params
     */
    saveParams: function (params) {
        store.save('saveable.' + this.element.attr('id'), params);
    }
});

/**
 * File upload widget.
 *
 * @link https://github.com/LPology/Simple-Ajax-Uploader
 */
$.widget("il.uploadable", {
    options: {
        multiple: false,
        maxFiles: 1000
    },
    maxFilesMessage: 'Maximum number of files reached.',
    maxSizeMessage:  'File <b>{{filename}}</b> exceeds the size limit of {{limit}} MB.',
    fileItem: `<div data-file="{{filename}}">
                   <div class="text-truncate">
                        {{filename}}
                   </div>
                   <div class="progress rounded-0 mb-2 bg-darker-5" style="height: 4px">
                       <div class="progress-bar" role="progressbar" style="width: 1%" aria-valuenow="1" aria-valuemin="0" aria-valuemax="100"></div>
                   </div>
               </div>`,
    _getCreateOptions: function() {
        return {
            multiple: this.element.find(':file').prop('multiple') === true ? true : false
        };
    },
    _create: function() {
        if (typeof IL.uploader === 'undefined') {
            IL.uploader = {};
        }
        // Bind form submit.
        this._on(this.element, {submit: this._submit});
        this._on(this.element.find('.uploadable-url'), {keydown: this._urlSubmit});
        this._on(this.element.find('.uploadable-clear'), {click: this._clearFiles});
        this._on(this.element.find(':file'), {change: this._pdfTitle});
        // Create SimpleUpload.
        let uploadable = this,
            buttonSelect = this.element.find('.uploadable-select'),
            buttonClear = this.element.find('.uploadable-clear'),
            listDiv = this.element.find('.uploadable-list'),
            maxSize = Math.min(window.MAX_UPLOAD, window.MAX_POST),
            totalSize = 0,
            loaded = 0;
        // Singleton in global space.
        IL.uploader[this.uuid] = new window.ss.SimpleUpload({
            button: buttonSelect,
            url: uploadable.element.attr('action'),
            name: uploadable.element.find(':file').attr('name'),
            form: uploadable.element,
            responseType: 'json',
            overrideSubmit: false,
            maxSize: maxSize / 1024,
            multiple: uploadable.options.multiple,
            multipleSelect: uploadable.options.multiple,
            autoSubmit: false,
            onChange: function (filename, extension, uploadBtn, fileSize, fileObj) {
                // Clear queue, if mutiple not allowed.
                if (uploadable.options.multiple === false) {
                    this.clearQueue();
                }
                // Max queue size.
                if (this.getQueueSize() >= uploadable.options.maxFiles) {
                    $.jGrowl(uploadable.maxFilesMessage, {header: 'Info', sticky: false, theme: 'bg-primary'});
                    return false;
                }
                // Add files to the list card.
                uploadable._addFile(fileObj);
                // Update total kB in queue.
                totalSize = totalSize + fileSize;
                // Show progress bar.
                listDiv.children('div').eq(0).removeClass('d-none');
                // Show clear button.
                buttonClear.removeClass('d-none');
                // Read PDF title.
                if (fileObj.type === 'application/pdf') {
                    uploadable._pdfTitle(fileObj);
                }
            },
            onSubmit: function (filename) {
                this.setProgressContainer(uploadable.element.find('div[data-file="' + _.escape(filename) + '"]'));
                this.setProgressBar(uploadable.element.find('div[data-file="' + _.escape(filename) + '"] .progress-bar'));
            },
            onSizeError: function (filename, fileSize) {
                let message = uploadable.maxSizeMessage.replace(/{{filename}}/, _.escape(filename)).replace(/{{limit}}/, maxSize / (1024 * 1024));
                $.jGrowl(message, {header: 'Info', sticky: false, theme: 'bg-primary'});
                totalSize = totalSize - fileSize;
                // Continue submitting.
                if (this.getQueueSize() > 0) {
                    this.submit();
                }
            },
            onDone: function (filename, status, statusText, response, uploadBtn, fileSize) {
                // Update total progress bar.
                loaded = loaded + fileSize;
                let totalPct = 100 * loaded / totalSize;
                uploadable.element.find('.uploadable-progress .progress-bar')
                    .attr('aria-valuenow', totalPct)
                    .css('width', totalPct + '%');
                // Continue submitting.
                if (this.getQueueSize() > 0) {
                    this.submit();
                }
            },
            onAllDone: function () {
                this.destroy();
                $.jGrowl('Upload has finished.', {header: 'Info', theme: 'bg-primary'});
                // Reload page when upload stops.
                B.history.loadUrl();
            },
            onError: function (filename, errorType, status, statusText, response) {
                // Trigger model._onFail method.
                let xhr = {
                    status: status,
                    statusText: statusText,
                    responseJSON: JSON.parse(response)
                };
                model._onFail(xhr);
                // Continue submitting.
                if (this.getQueueSize() > 0) {
                    this.submit();
                }
            }
        });
    },
    /**
     * Add file element to the list.
     *
     * @param fileObj
     */
    _addFile: function (fileObj) {
        let item = this.fileItem.replace(/{{filename}}/g, _.escape(fileObj.name)),
            $cont = this.element.find('.uploadable-list').find('.list-group-item').eq(1).children();
        if (this.options.multiple === true) {
            $cont.append(item);
        } else {
            $cont.html(item);
        }
        this.element.find('.uploadable-list').removeClass('d-none');
        this._trigger('change', null, fileObj);
    },
    /**
     * Submit uploader manually.
     *
     * @param {object} e Event.
     */
    _submit: function (e) {
        e.preventDefault();
        if (typeof IL.uploader[this.uuid] === 'object' && IL.uploader[this.uuid].getQueueSize() > 0) {
            // We have file, submit using the plugin.
            IL.uploader[this.uuid].submit();
        } else {
            // No file, submit natively.
            $.when(model.save({url: this.element.attr('action'), data: this.element.serialize()})).done(function () {
                B.history.loadUrl();
            });
        }
    },
    /**
     * Catch enter key in URL input.
     *
     * @param {object} e Event.
     */
    _urlSubmit: function (e) {
        if (e.which === 13) {
            this.element.trigger('submit');
            return false;
        }
    },
    /**
     * Clear files from the UI and the plugin queue.
     */
    _clearFiles: function () {
        // Hide the file list.
        this.element.find('.uploadable-list').addClass('d-none');
        this.element.find('.uploadable-list').find('.list-group-item').eq(1).children().empty();
        // Hide clear button.
        this.element.find('.uploadable-clear').addClass('d-none');
        IL.uploader[this.uuid].clearQueue();
        this._trigger('clear');
    },
    /**
     * Read PDF titles client-side.
     *
     * @param   FileListObj
     * @returns {Boolean}
     */
    _pdfTitle: function (FileListObj) {
        // Only if pdftitles callback is detected.
        if (typeof this.options.pdftitles !== 'function') {
            return false;
        }
        // Limit to <=5 MB files, eats a lot of CPU, memory.
        if(FileListObj.size > 5 * 1024 * 1024) {
            this._trigger('pdftitles', null, {titles: []});
            return false;
        }
        // Read PDF title.
        let widget = this, reader = new FileReader();
        let extractTitles = function () {
            let content = reader.result,
                regex = new RegExp(/<dc:title[\s\S]+?\/dc:title>/g),
                result,
                titles = [],
                i = 0;
            while ((result = regex.exec(content))) {
                let $xml = $(result[0]);
                titles[i] = $.trim($xml.text());
                i++;
            }
            titles = titles.filter(Boolean);
            let titleOutput = titles.length === 0 ? [] : titles;
            widget._trigger('pdftitles', null, {titles: titleOutput});
            // Clean up.
            reader.removeEventListener("load", extractTitles);
            reader = null;
        };
        reader.addEventListener("load", extractTitles);
        reader.readAsText(FileListObj);
    }
});

/**
 * jGrowl notification defaults.
 */
$.jGrowl.defaults.pool = 3;
$.jGrowl.defaults.life = 5000;
$.jGrowl.defaults.position = 'top-right';
$.jGrowl.defaults.closeTemplate = '<span class="mdi mdi-window-close" aria-label="Close alert"></span>';
$.jGrowl.defaults.closerTemplate = '<button>CLOSE ALL</button>';

/*
 * Classes.
 */

/**
 * @class Overlay. Use as singleton.
 */
class Overlay {
    constructor() {
        this.delay  = 800;
        this.timeId = null;
        /**
         * @property {string} Icon options: loading, radar, orbit...
         */
        this.template = `<div id="overlay" class="d-flex align-items-center" style="display:none" aria-hidden="true">
                             <span class="mx-auto mdi mdi-orbit mdi-spin text-danger"></span>
                         </div>`;
    }
    /**
     * Start overlay.
     * @param   {number}  delay
     * @returns {boolean}
     */
    start(delay) {
        let This = this;
        // Only one overlay at a time.
        if (typeof this.timeId === 'number') {
            this.stop();
        }
        delay = typeof delay === 'number' ? delay : this.delay;
        // Create overlay.
        this.timeId = setTimeout(function () {
            let $el = $('#overlay');
            if ($el.length === 0) {
                $('body').append(This.template);
                $el.fadeIn(250, function () {});
            }
        }, delay);
        return true;
    }
    /**
     * Stop overlay.
     * @returns {boolean}
     */
    stop() {
        let $el = $('#overlay');
        clearTimeout(this.timeId);
        this.timeId = null;
        if ($el.length > 0) {
            $el.fadeOut(150, function () {
                $el.remove();
            });
        }
        return true;
    }
}

let overlay = new Overlay();

/**
 * @class LocalStore. Simple storage.
 */
class LocalStore {
    save(key, value) {
        // Do not save empty values and functions.
        if (value === undefined || value === null || typeof value === 'function') {
            return false;
        }
        localStorage.setItem(key, this._serialize(value));
    }
    /**
     * @param  {string} key
     * @return {mixed|null}
     */
    load(key) {
        return this._unserialize(localStorage.getItem(key));
    }
    delete(key) {
        localStorage.removeItem(key);
    }
    clear() {
        localStorage.clear();
    }
    _serialize(value) {
        return JSON.stringify(value);
    }
    _unserialize(value) {
        return JSON.parse(value);
    }
}

let store = new LocalStore();

/**
 * @class LocalStore. Simple storage.
 */
class SessionStore {
    save(key, value) {
        // Do not save empty values and functions.
        if (value === undefined || value === null || typeof value === 'function') {
            return false;
        }
        sessionStorage.setItem(key, this._serialize(value));
    }
    /**
     * @param  {string} key
     * @return {mixed|null}
     */
    load(key) {
        return this._unserialize(sessionStorage.getItem(key));
    }
    delete(key) {
        sessionStorage.removeItem(key);
    }
    clear() {
        sessionStorage.clear();
    }
    _serialize(value) {
        return JSON.stringify(value);
    }
    _unserialize(value) {
        return JSON.parse(value);
    }
}

let sessionStore = new SessionStore();

/**
 * @class Checkbox/radio beautification.
 */
class FormStyle {
    init() {
        let This = this;
        $(':checkbox, :radio').each(function () {
            This.updateStyle($(this));
        }).off('change.formstyle').on('change.formstyle', function () {
            This.changeState($(this));
        }).off('focus.formstyle').on('focus.formstyle', function () {
            // Show focus for better accessibility.
            $(this).next().find('.label-text').css('text-decoration', 'dotted underline');
        }).off('blur.formstyle').on('blur.formstyle', function () {
            // Remove focus for better accessibility.
            $(this).next().find('.label-text').css('text-decoration', 'none');
        });
    }
    changeState(el) {
        let This = this;
        $('input[name="' + el.attr('name') + '"]').each(function () {
           This.updateStyle($(this));
        });
    }
    updateStyle(el) {
        let $icon = el.next().find('.mdi');
        if (el.attr('type') === 'radio') {
            if (el.prop('checked') === true) {
                $icon
                    .removeClass('mdi-radiobox-blank')
                    .removeClass('text-muted')
                    .addClass('mdi-radiobox-marked text-primary');
            } else {
                $icon
                    .removeClass('mdi-radiobox-marked')
                    .removeClass('text-primary')
                    .addClass('mdi-radiobox-blank text-muted');
            }
        } else if (el.attr('type') === 'checkbox') {
            if (el.prop('checked') === true) {
                $icon
                    .removeClass('mdi-checkbox-blank-outline')
                    .removeClass('text-muted')
                    .addClass('mdi-checkbox-marked text-primary');
            } else {
                $icon
                    .removeClass('mdi-checkbox-marked')
                    .removeClass('text-primary')
                    .addClass('mdi-checkbox-blank-outline text-muted');
            }
        }
    }
    updateForm($form) {
        let This = this;
        $form.find(':checkbox, :radio').each(function () {
            This.updateStyle($(this));
        });
    }
}

let formStyle = new FormStyle();

class Keyboard {
    init() {
        let $kw = $('#keyboard-window');
        // Toggle window.
        if ($kw.hasClass('d-lg-block') === true) {
            $kw.removeClass('d-lg-block');
            return;
        } else {
            $kw.addClass('d-lg-block');
        }
        // Load.
        if ($kw.length === 0) {
            $.when(model.load({
                url: window.IL_BASE_URL + 'index.php/keyboard'
            })).done(function (response) {
                $('body').append(response.html);
                let $kw = $('#keyboard-window');
                // Init.
                $kw.position({
                    my: 'bottom',
                    at: 'bottom',
                    of: 'body'
                });
                $(window).off('resize.keyboard').on('resize.keyboard', function () {
                    $kw.position({
                        my: 'bottom',
                        at: 'bottom',
                        of: 'body'
                    });
                });
                $kw.draggable({
                    handle: ".card-header",
                    containment: "body"
                });
                // Close button.
                $kw.find('.close').off('click.keyboard').on('click.keyboard', function () {
                    $kw.removeClass('d-lg-block');
                });
                $kw.find('.tab-content').on('mousedown', function () {
                    return false;
                });
                $('#keyboard').on('click', '.btn', function () {
                    let char = $(this).html();
                    if ($('#notes-ta_ifr').length === 1) {
                        tinymce.execCommand("mceInsertContent", !1, char);
                    } else {
                        $(':focus').insertAtCaret(char).trigger('input');
                    }
                });
            });
        }
    }
}

let keyboard = new Keyboard();

class ExportForm {
    init() {
        let exportUrl = decodeURIComponent($('#open-export').data('exportUrl')),
            ctrl = exportUrl.indexOf('index.php/summary') > 0 ? 'summary' : 'items',
            $w = $('#modal-export');
        // Load.
        if ($.trim($w.find('.modal-body').html()) === '') {
            $.when(model.load({
                url: window.IL_BASE_URL + 'index.php/' + ctrl + '/exportform'
            })).done(function (response) {
                $w.find('.modal-body').append(response.html);
                formStyle.init();
                $('#export-form').saveable();
                $w.find('.modal-footer button:first-child').off('click').on('click', function () {
                    exportform.formSubmit();
                });
                $('#export-styles').typeahead();
                $('#export-styles').off('focus').on('focus', function () {
                    $w.find('[name="export"]').val(['citation']);
                    formStyle.updateForm($('#export-form'));
                });
            });
        }
    }
    formSubmit() {
        let exportUrl = decodeURIComponent($('#open-export').data('exportUrl')),
            $f = $('#export-form'),
            urlObj = new URL(exportUrl),
            sep = urlObj.search === '' ? '?' : '&';
        window.open(exportUrl + sep + $f.serialize());
        $f.saveable('save');
    }
}

let exportform = new ExportForm();

class OmnitoolForm {
    init() {
        let $w = $('#modal-omnitool');
        // Load.
        if ($.trim($w.find('.modal-body').html()) === '') {
            $.when(model.load({
                url: window.IL_BASE_URL + 'index.php/items/omnitoolform'
            })).done(function (response) {
                $w.find('.modal-body').append(response.html);
                formStyle.init();
                $w.find('.modal-footer button:first-child').on('click', function () {
                    omnitoolform.formSubmit();
                });
                $('#tag-filter-omnitool').filterable({
                    complete: function () {
                        $('#omnitool-tags .label-text').each(function() {
                            if($(this).hasClass('d-none')) {
                                $(this).parent().parent().addClass('d-none');
                            } else {
                                $(this).parent().parent().removeClass('d-none');
                            }
                        });
                        $('#omnitool-tags .tag-table').find('tr').each(function() {
                            if($(this).find('.label-text').length - $(this).find('.label-text.d-none').length === 0) {
                                $(this).addClass('d-none');
                            } else {
                                $(this).removeClass('d-none');
                            }
                        });
                    }
                });
            });
        } else {
            let $f = $w.find('form');
            $f[0].reset();
            formStyle.updateForm($f);
        }
    }
    formSubmit() {
        let omnitoolUrl = decodeURIComponent($('#open-omnitool').data('omnitoolUrl')),
            $f = $('#modal-omnitool').find('form');
        $('#modal-omnitool').modal('hide');
        $.when(model.save({
            url: omnitoolUrl,
            data: $f.serialize()
        })).done(function () {
            B.history.loadUrl();
        });
    }
}

let omnitoolform = new OmnitoolForm();

class QuickSearch {
    init() {
        let This = this, $f = $('#quick-search-form');
        $f.off('submit').on('submit', function (e) {
            e.preventDefault();
            This.formSubmit();
        });
        $('#modal-quick-search .modal-footer').find('button.search-submit').off('click').on('click', function () {
            This.formSubmit();
        });
        // Saveable should appear after clonable, so it can clone/load extra rows.
        $f.saveable();
    }
    formSubmit() {
        let $m = $('#modal-quick-search'), $f = $('#quick-search-form'),
            urlObj = new URL('http://foo.bar/' + $f.attr('action').substr(1)),
            sep = urlObj.search === '' ? '?' : '&';
        $f.saveable('save');
        router.navigate($f.attr('action') + sep + $f.serialize(), {trigger: true});
        $m.modal('hide');
    }
}

let quicksearch = new QuickSearch();

class AdvancedSearch {
    init() {
        let This = this, $f = $('#advanced-search-form');
        $f.off('submit').on('submit', function (e) {
            e.preventDefault();
            This.formSubmit();
        });
        $('#modal-advanced-search .modal-footer').find('button.search-submit').off('click').on('click', function () {
            This.formSubmit();
        });
        $('#advanced-search-form .clone-button').clonable({
            target: '#clone-target',
            onClone: function (e, t) {
                // This hides booleans for string fields.
                $(t.clonedTarget).find('select.fields').trigger('change');
            }
        });
        // Saveable should appear after clonable, so it can clone/load extra rows.
        $f.saveable();
        $f.on('change', 'select.fields', this.hideBooleans);
        this.hideBooleans();
        $('#tag-filter-search').filterable({
            complete: function () {
                $('#search-tags .label-text').each(function() {
                    if($(this).hasClass('d-none')) {
                        $(this).parent().parent().addClass('d-none');
                    } else {
                        $(this).parent().parent().removeClass('d-none');
                    }
                });
                $('#search-tags .tag-table').find('tr').each(function() {
                    if($(this).find('.label-text').length - $(this).find('.label-text.d-none').length === 0) {
                        $(this).addClass('d-none');
                    } else {
                        $(this).removeClass('d-none');
                    }
                });
            }
        });
    }
    /**
     * Save and submit search form.
     */
    formSubmit() {
        let $m = $('#modal-advanced-search'), $f = $('#advanced-search-form'),
            urlObj = new URL('http://foo.bar/' + $f.attr('action').substr(1)),
            sep = urlObj.search === '' ? '?' : '&';
        $f.saveable('save');
        router.navigate($f.attr('action') + sep + $f.serialize(), {trigger: true});
        $m.modal('hide');
    }
    /**
     * Show/hide boolean radios, based on search field type.
     * @param e
     */
    hideBooleans (e) {
        let hide, selects = e === undefined ? $('#advanced-search-form').find('select.fields') : $(this);
        $.each(selects, function (i, select) {
            switch ($(select).val()) {
                case 'AU':
                case 'T1':
                case 'T2':
                case 'T3':
                case 'KW':
                case 'YR':
                case 'C1':
                case 'C2':
                case 'C3':
                case 'C4':
                case 'C5':
                case 'C6':
                case 'C7':
                case 'C8':
                    hide = true;
                    break;
                default:
                    hide = false;
            }
            if (hide) {
                $(select).closest('.row').removeClass('text-search').addClass('string-search');
            } else {
                $(select).closest('.row').removeClass('string-search').addClass('text-search');
            }
        });
    }
}

let advancedsearch = new AdvancedSearch();

class SearchList {
    init() {
        let $w = $('#modal-searches');
        $w.on('show.bs.modal', searchlist.load);
    };
    load(e) {
        let $w = $('#modal-searches');
        $.when(model.load({
            url: window.IL_BASE_URL + 'index.php/search/list',
            async: true
        })).done(function (response) {
            $w.find('.modal-body').html(response.html);
            $w.find('.modal-body').on('click', '.delete-search', searchlist.deleteSearch);
        });
    }
    deleteSearch(e) {
        let $t = $(this);
        $.when(model.save({
            url: $t.data('url'),
            data: {
                id: $t.data('id')
            }
        })).done(function () {
            searchlist.load();
        });
    }
}

let searchlist = new SearchList();

class Sidebar {
    init() {
        let This = this, $sm = $('#side-menu'), $nt = $('.navbar-toggler');
        // Expand menu on hash change.
        $(window).on('popstate', function () {
            let hashParts = location.hash.split('?'), path = hashParts[0],
                $l = $sm.find('a[href^="' + path + '"]');
            if ($l.length === 0) {
                return;
            }
            // Reset menu.
            $sm.find('a').removeClass('clicked');
            if (location.hash === '' || location.hash === '#') {
                return true;
            }
            This.expandSidebar($l);
        });
        // Initial page open.
        $sm.metisMenu();
        let hashParts = location.hash.split('?'), path = hashParts[0],
            $l = $sm.find('a[href^="' + path + '"]');
        if ($l.length === 0) {
            // Link not found. Pop the action and try again.
            let h = _.dropRight(location.hash.split('/'));
            $l = $sm.find('a[href="' + h + '"]');
        }
        this.expandSidebar($l);
        // Collapse menu after click on mobile.
        $sm.on('click', 'a', function () {
            if (location.hash === $(this).attr('href')) {
                B.history.loadUrl();
            }
            if ($(this).attr('href') !== '' && $(this).attr('href') !== '#' && $nt.is(':visible')) {
                $nt.trigger('click');
            }
        });
    }
    expandSidebar($l) {
        $l.parents('ul.collapse').not('.in').each(function () {
            let $t = $(this);
            $t.addClass('in').prev().click();
        });
        $l.addClass('clicked');
    }
}

let sidebar = new Sidebar();

class ILChart {
    constructor() {
        // Chart.js is not always loaded.
        if (window.Chart !== undefined) {
            window.Chart.defaults.global.defaultFontFamily = 'Noto Sans';
            window.Chart.defaults.global.defaultFontColor = '#777777';
            window.Chart.plugins.register({
                beforeDatasetsDraw: function(chartInstance, easing) {
                    let ctx = chartInstance.chart.ctx;
                    let chartArea = chartInstance.chartArea;
                    if (chartInstance.options.drawBorder) { // custom option to turn on drawing
                        ctx.strokeStyle = chartInstance.options.borderColor; // custom option for color
                        ctx.lineWidth = 1;
                        ctx.strokeRect(
                            chartArea.left + 0.5,
                            chartArea.top + 0.5,
                            chartArea.right - chartArea.left - 0.5,
                            chartArea.bottom - chartArea.top - 0.5
                        );
                    }
                }
            });
        }
    }
    draw(elementId, labels, title1, dataset1, title2, dataset2) {
        let $el = $('#' + elementId),
            legend = typeof title1 !== 'undefined' && title1 !== null,
            datasets = [];
        if ($el.length === 1) {
            let ctx = $el.get(0).getContext('2d');
            datasets.push(chart._dataset1(dataset1, title1));
            if(dataset2 !== undefined && dataset2 !== null) {
                datasets.push(chart._dataset2(dataset2, title2));
            }
            if (typeof window.charts[elementId] !== 'undefined') {
                window.charts[elementId].destroy();
            }
            window.charts[elementId] = new window.Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: chart._options(labels, legend, dataset2)
            });
        }
    }
    _dataset1(data, title) {
        let primaryColor = '#2f8ded';
        return {
            label: title,
            data: data,
            yAxisID: 'y-axis-1',
            fill: false,
            borderWidth: 2,
            backgroundColor: primaryColor,
            borderColor: primaryColor,
            lineTension: 0.2,
            pointBorderColor: primaryColor,
            pointBackgroundColor: primaryColor,
            pointHoverBackgroundColor: primaryColor,
            pointHoverBorderColor: primaryColor,
            pointHitRadius: 8,
            pointRadius: 2
        };
    }
    _dataset2(data, title) {
        let secondaryColor = '#ed9b25';
        return {
            label: title,
            data: data,
            yAxisID: 'y-axis-2',
            fill: false,
            borderWidth: 2,
            backgroundColor: secondaryColor,
            borderColor: secondaryColor,
            lineTension: 0.2,
            pointBorderColor: secondaryColor,
            pointBackgroundColor: secondaryColor,
            pointHoverBackgroundColor: secondaryColor,
            pointHoverBorderColor: secondaryColor,
            pointHitRadius: 8,
            pointRadius: 2
        };
    }
    _options (labels, legend, dataset2) {
        let yAxes = [], yAxis1 = {
            type: 'linear',
            display: true,
            position: 'left',
            id: 'y-axis-1',
            gridLines: {
                color: '#999',
                tickMarkLength: 0
            },
            ticks: {
                maxTicksLimit: 5,
                beginAtZero: true,
                precision: 0,
                padding: 8
            }
        }, yAxis2 = {
            type: 'linear',
            display: true,
            position: 'right',
            id: 'y-axis-2',
            gridLines: {
                drawOnChartArea: false,
                color: '#999',
                tickMarkLength: 0
            },
            ticks: {
                maxTicksLimit: 5,
                beginAtZero: true,
                precision: 0,
                padding: 8
            }
        };
        yAxes.push(yAxis1);
        if(dataset2 !== undefined && dataset2 !== null) {
            yAxes.push(yAxis2);
        }
        return {
            responsive: true,
            maintainAspectRatio: false,
            stacked: false,
            legend: {
                display: legend
            },
            drawBorder: true,
            borderColor: '#999',
            scales: {
                xAxes: [{
                    type: 'time',
                    time: {
                        displayFormats: {
                            'hour': 'M/D',
                            'day': 'M/D',
                            'week': 'M/D',
                            'month': 'YYYY/M',
                            'quarter': 'YYYY/M',
                            'year': 'YYYY'
                        },
                        unit: labels.length > 13 ? 'day' : 'month'
                    },
                    gridLines: {
                        color: '#999',
                        tickMarkLength: 0
                    },
                    ticks: {
                        padding: 8
                    }
                }],
                yAxes: yAxes
            }
        };
    }
}

let chart = new ILChart();

/**
 * Image CSS filter formatter.
 */
class ImageFilter {
    constructor() {
        this.touchDevice = window.matchMedia('(max-width: 1199px)').matches;
        this.default = {
            contrast:   '1',
            brightness: '1',
            saturation: '1',
            hue:        '0deg',
            invert:     '0',
            sharpen:     false,
            sharpness:  '0'
        };
    }
    /**
     * Compile CSS filter string.
     * @param {boolean} sharpen Sharpen or not.
     * @returns {string}
     */
    compileCss(sharpen) {
        let nightMode = $('.pdfviewer-right > div:first').hasClass('img-night-mode'),
            contrast = $('#adjust-contrast').val() || this.default.contrast,
            brightness = $('#adjust-brightness').val() || this.default.brightness,
            saturation = $('#adjust-saturation').val() || this.default.saturation,
            sharpness = $('#adjust-sharpness').val() || this.default.sharpness,
            hue = nightMode ? '180deg' : '0deg',
            invert = nightMode ? '1' : '0';
        let f = `contrast(${contrast}) brightness(${brightness}) saturate(${saturation}) hue-rotate(${hue}) invert(${invert})`;
        if (sharpen === true && sharpness !== '0') {
            f = f + ' url(#sharpen)';
        }
        return f;
    }
    /**
     * Adjust CSS filter for the page number.
     * @param {number} page
     */
    adjustFilter(page) {
        if (this.touchDevice) {
            return;
        }
        $('.pdfviewer-right img').slice(Math.max(page - 3, 0), page + 3).css('filter', this.compileCss(true));
    }
    /**
     * Adjust SVG sharpness filter for the page number.
     * @param {number} page
     */
    adjustSharpness(page) {
        if (this.touchDevice) {
            return;
        }
        let $svg = $('#sharpen feConvolveMatrix'), matrix = $svg.attr('kernelMatrix').split(' ');
        matrix[4] = -12 * parseFloat($('#adjust-sharpness').val()) + 16;
        $svg.attr('kernelMatrix', matrix.join(' '));
        this.adjustFilter(page);
    }
    /**
     * Remove SVG sharpness filter for the page number. Improves scrolling performance.
     * @param {number} page
     */
    disableSharpening(page) {
        if (this.touchDevice) {
            return;
        }
        $('.pdfviewer-right img').slice(Math.max(page - 3, 0), page + 3).css('filter', this.compileCss(false));
    }
    /**
     * Reset SVG sharpness and CSS filters for the page number to default values.
     * @param {number} page
     */
    reset(page) {
        if (this.touchDevice) {
            return;
        }
        $('#adjust-contrast').val(this.default.contrast);
        $('#adjust-brightness').val(this.default.brightness);
        $('#adjust-saturation').val(this.default.saturation);
        $('#adjust-sharpness').val(this.default.sharpness);
        this.adjustSharpness(page);
    }
    lightMode() {
        $('.pdfviewer-left img').css('filter', 'hue-rotate(0deg) invert(0)');
        $('.pdfviewer-right img').css('filter', this.compileCss(true));
    }
    nightMode() {
        $('.pdfviewer-left img').css('filter', 'hue-rotate(180deg) invert(1)');
        $('.pdfviewer-right img').css('filter', this.compileCss(true));
    }
}

let imageFilter = new ImageFilter();

let arxiv = {
    url: 'https://export.arxiv.org/api/query?start=0&max_results=1&id_list=',
    metadata: function (input) {
        let output = {
            'author_last_name': [],
            'author_first_name': [],
            'keywords': [],
            'uid_types': [],
            'uids': [],
            'secondary_title': 'eprint'
        }, entry = input.find('entry');
        output.reference_type = 'article';
        output.title = entry.children('title').text().replace(/\r?\n|\r/g, ' ') || '';
        output.abstract = entry.children('summary').text().replace(/\r?\n|\r/g, ' ') || '';
        let d = new Date(entry.children('published').text().replace(/\r?\n|\r/g, ' '));
        output.publication_date = d.toISOString().substr(0, 10);
        let doi = entry.children('arxiv\\:doi').text().replace(/\r?\n|\r/g, ' ') || '';
        if (doi !== '') {
            output.uid_types.push('DOI');
            output.uids.push(doi);
        }
        if (entry.find('id').length === 1) {
            output.uid_types.push('ARXIV');
            let arxivId = entry.find('id').text().replace(/https?:\/\/arxiv\.org\/abs\//, '');
            output.uids.push(arxivId);
            $('.uploadable-url').val('https://arxiv.org/pdf/' + arxivId);
        }
        _.forEach(entry.find('name'), function (author, key) {
            let parts = $(author).text().split(' ');
            output.author_last_name[key] = parts.pop();
            output.author_first_name[key] = parts.join(' ');
        });
        _.forEach(entry.children('category'), function (category, key) {
            output.keywords[key] = $(category).attr('term');
        });
        return JSON.stringify(output);
    }
};

/*
 * MVC.
 */

/**
 * Container object to register views.
 * @type object
 */
let views = {};

/**
 * Backbone-based MVC structure.
 */
B.emulateJSON = true;

/**
 * Router extends Backbone's Router.
 */
let Router = B.Router.extend({
    /**
     * Routes: route => dispatcher
     */
    routes: {
        ":controller(/:action)(?:query)": "dispatch"
    },
    /**
     * Dispatch the controller and the correct view.
     *
     * @param {string} uriController
     * @param {string} uriAction
     * @param {string} uriQuery
     */
    dispatch: function (uriController, uriAction, uriQuery) {
        let c = typeof uriController === 'string' ? uriController : 'main';
        let a = typeof uriAction === 'string' ? uriAction : 'main';
        let q = typeof uriQuery === 'string' ? '?' + uriQuery : '';
        $.when(model.load({url: window.IL_BASE_URL + 'index.php/' + c + '/' + a + q})).done(function (response) {
            if (typeof views[(c + a)] === 'object') {
                views[(c + a)].render(response);
            } else {
                console.error('The view "' + (c + a) + '" does not exist.');
            }
        });
    }
});

let router = new Router();

/**
 * Model. Receives requests from the router or a view, contacts the server
 * and returns a response.
 */
class Model {
    constructor() {
        this.xhr = '';
        this.options = {
            url: null,
            data: undefined,
            async: false,
            cors: false,
            dataType: 'json'
        };
    }
    load(options) {
        options = _.assign({}, this.options, options);
        return this._request('GET', options.url, options.data, options.async, options.cors, options.dataType);
    }
    save(options) {
        options = _.assign({}, this.options, options);
        return this._request('POST', options.url, options.data, options.async, options.cors, options.dataType);
    }
    _request(method, url, data, async, cors, dataType) {
        let This = this,
            Dfrd = $.Deferred(),
            block = async !== true,
            dataType2 = dataType || 'json';
        // Abort previous request if async is false.
        if (block === true && typeof this.xhr === 'object') {
            this.abort();
        }
        let customHeader = {'X-Client-Width': screen.width};
        if (cors === true) customHeader = {};
        let xhrObj = $.ajax({
            url: url,
            data: data,
            type: method,
            dataType: dataType2,
            headers: customHeader
        }).done(function (response) {
            This._onSuccess(response);
            Dfrd.resolve(response);
        }).fail(function (xhr) {
            This._onFail(xhr, cors);
            Dfrd.reject(xhr);
        });
        // Blocking request locks.
        if (block === true) {
            overlay.start();
            model.xhr = xhrObj;
        }
        return Dfrd;
    }
    abort() {
        if (typeof this.xhr === 'object') {
            this.xhr.abort();
            this.xhr = '';
            overlay.stop();
        }
    }
    _onSuccess(response) {
        this.xhr = '';
        overlay.stop();
        if (typeof response.info === 'string') {
            $.jGrowl(response.info, {header: 'Info', sticky: false, theme: 'bg-primary'});
        }
    }
    _onFail(xhr, cors) {
        this.xhr = '';
        overlay.stop();
        if (cors === false || cors === undefined) {
            if (xhr.statusText === 'abort') {
            } else if (xhr.status === 401) {
                let redir = window.IL_BASE_URL + '?ref=' + window.btoa(location.href);
                // Not authenticated.
                location.replace(redir);
            } else if (xhr.status === 404) {
                // Not found.
                location.replace(window.IL_BASE_URL + 'index.php/notfound/main');
            } else if (xhr.status > 400 && xhr.status < 500) {
                // Client errors.
                let errMessage = typeof xhr.responseJSON !== 'undefined' ?
                    xhr.responseJSON.error :
                    'Unspecified error.';
                $.jGrowl(errMessage, {header: 'Info', sticky: false, theme: 'bg-primary'});
            } else {
                // 500 server errors.
                let errMessage = typeof xhr.responseJSON !== 'undefined' ?
                    xhr.responseJSON.error :
                    'Unspecified error.';
                $.jGrowl(errMessage, {header: xhr.status + ' ' + xhr.statusText, sticky: true, theme: 'bg-danger'});
            }
        }
    }
}

let model = new Model();

/**
 * View. Abstract class.
 */
class View {
    constuctor() {
        this.events = {};
        this.parent = '';
    }
    /**
     * Render. Called by the Router when a response arrives.
     * @param  {object} data
     * @return {View|Boolean}
     */
    render(data) {
        let $par = $(this.parent);
        if ($par.length !== 1) {
            console.error('The view container "' + this.parent + '" not found!');
            return false;
        }
        if (typeof data.title === 'string') {
            document.title = data.title;
        }
        if (typeof data.html === 'string') {
            // Inject HTML.
            $par.html(data.html);
            this.delegateEvents();
            if (typeof window.MathJax.typeset === 'function') {
                window.MathJax.typeset();
            }
            this.afterRender(data);
        }
        // Hide modals.
        $('.modal').modal('hide');
        $('.modal-backdrop').remove();
        $('[data-toggle="tooltip"], .tooltip').tooltip("hide");
        return this;
    }
    /**
     * This render can be called called by HTML pages.
     * @return {View}
     */
    htmlRender() {
        // Bind events.
        for (let key in this.events) {
            // Method can be a name or a function literal.
            let method = this.events[key];
            if (!_.isFunction(method)) {
                method = this[method];
            }
            // Process event name and source.
            let match = key.match(/^(\S+)\s*(.*)$/);
            // Bind method and stop event bubbling.
            $('body').on(match[1] + '.view', match[2], {object: this}, method);
        }
        // Form beautification.
        formStyle.init();
        if (typeof window.MathJax.typeset === 'function') {
            window.MathJax.typeset();
        }
        this.afterRender();
    }
    /**
     * After HTML is inserted and events are delegated.
     * @param {object|undefined} data
     */
    afterRender(data = undefined) {}
    /**
     * Delegate events to the parent element.
     * @return {View}
     */
    delegateEvents() {
        // Nothing to delegate.
        if (typeof this.events === 'undefined') {
            return this;
        }
        // Remove all events from the parent element.
        this.undelegateEvents();
        for (let key in this.events) {
            // Method can be a name or a function literal.
            let method = this.events[key];
            if (!_.isFunction(method)) {
                method = this[method];
            }
            // Process event name and source.
            let match = key.match(/^(\S+)\s*(.*)$/);
            // Bind method and stop event bubbling.
            $(this.parent).on(match[1] + '.view', match[2], {object: this}, method);
        }
        return this;
    }
    undelegateEvents() {
        if ($(this.parent).length === 1) {
            $(this.parent).off('.view');
        }
        return this;
    }
}

/*
 * Extended XHR views.
 */

/**
 * Dashboard view.
 */
class DashboardMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
    }
    afterRender(data) {
        formStyle.init();
        quicksearch.init();
        advancedsearch.init();
        let pages_read = typeof data.pages_read === 'undefined' ||
            data.pages_read.length === 0 ? null : Object.values(Object.values(data.pages_read)[0]),
            pages_read_label = pages_read === null ? null : Object.keys(data.pages_read);
        chart.draw(
            'myChart',
            Object.keys(Object.values(data.activity)[0]),
            Object.keys(data.activity),
            Object.values(Object.values(data.activity)[0]),
            pages_read_label,
            pages_read
        );
        sessionStore.delete('il.idList');
    }
}

views.dashboardmain = new DashboardMainView();

/**
 * Settings view.
 */
class SettingsMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit #user-settings-form': 'saveForm'
        };
    }
    afterRender(data) {
        // Form beautification.
        formStyle.init();
        $('#timezones').typeahead();
        // Delete settings modal body to prevent conflicts.
        $('#modal-settings').find('.modal-body').html('');
    }
    saveForm(e) {
        e.preventDefault();
        let $f = $(this);
        // Add unchecked boxes as hidden inputs.
        $f.find(':checkbox').each(function () {
            if ($(this).prop('checked') === false) {
                $f.append(`<input type="hidden" name="${$(this).attr('name')}" value="0">`);
            }
        });
        $.when(model.save({
            url: $f.attr('action'),
            data: $f.serialize()
        })).done(function () {
            location.reload();
        });
    }
}

views.settingsmain = new SettingsMainView();

/**
 * Profile view.
 */
class ProfileMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit #user-profile-form': 'saveProfile',
            'submit #user-profile-change-password': 'savePassword'
        };
    }
    afterRender(data) {
        $('[data-toggle="tooltip"]').tooltip({
            container: '#content-col'
        });
    }
    saveProfile(e) {
        e.preventDefault();
        let $f = $(this);
        $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function () {
            let fname = $('#first_name').val() === '' ? $('#username').val() : $('#first_name').val();
            $('#menu-first-name').text(fname);
        });
    }
    savePassword(e) {
        e.preventDefault();
        let $f = $(this);
        $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function () {
            $f[0].reset();
        });
    }
}

views.profilemain = new ProfileMainView();

/**
 * Library view.
 */
class ItemsMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'click .open-filter-local': 'filterLocal',
            'click .open-filter-remote': 'filterRemote',
            'click .clipboard': 'clipboard',
            'click .project': 'project',
            'click #open-export': exportform.init,
            'click #open-omnitool': omnitoolform.init
        };
    }
    afterRender(data) {
        // Form beautification.
        formStyle.init();
        let This = this;
        this.itemsHeight();
        $(window).off('resize.ItemsMainView').on('resize.ItemsMainView', function () {
            This.itemsHeight();
        });
        quicksearch.init();
        advancedsearch.init();
        sessionStore.save('il.idList', data.id_list);
        this.highlightSearch();
    }
    itemsHeight() {
        let bH = $('#bottom-row').outerHeight();
        // Horizontal vs. vertical layout.
        if ($('.navbar-toggler').is(':visible')) {
            $('#top-row').height($(window).height()
                - bH
                - $('.navbar-toggler').outerHeight(true)
                - ($('#filter-list').length === 1 ? $('#filter-list').outerHeight(true) : 0));
        } else {
            $('#top-row').height($(window).height() - bH);
        }
    }
    filterLocal() {
        let src = $(this).data('src'), ttl = $(this).data('title');
        $.when(model.load({url: src})).done(function (response) {
            $('#modal-filters .modal-title').html(ttl);
            $('#modal-filters .modal-body .container-fluid').replaceWith(response.html);
            $('#modal-filters').modal();
            $('#modal-filters .modal-body').on('click', 'a', function () {
                $('#modal-filters').modal('hide');
            });
            // Destroy existing filter widget.
            $('#modal-filters .filterable').filterable('destroy');
            // Initiate new filter.
            $('#modal-filters :text').filterable({targets: '#modal-filters a'}).val('').focus();
        });
    }
    filterRemote() {
        let src = $(this).data('src'), ttl = $(this).data('title');
        $.when(model.load({url: src})).done(function (response) {
            $('#modal-filters .modal-title').html(ttl);
            $('#modal-filters .modal-body .container-fluid').replaceWith(response.html);
            $('#modal-filters').modal();
            $('#modal-filters .modal-body').on('click', 'a', function () {
                $('#modal-filters').modal('hide');
            });
            // Destroy existing filter widget.
            $('#modal-filters .filterable').filterable('destroy');
            // Initiate new filter.
            $('#modal-filters :text').filterable({
                source: src,
                container: '#modal-filters .container-fluid'
            }).val('').focus();
        });
    }
    clipboard() {
        let $t = $(this), itemId = $t.closest('.item-container').data('id');
        let action = $t.prop('checked') === true ? 'add' : 'delete';
        $.when(model.save({url: window.IL_BASE_URL + 'index.php/clipboard/' + action, data: {id: itemId}})).fail(function () {
            // Revert the checkbox state.
            $t.prop('checked', !$t.prop('checked'));
            formStyle.changeState($t);
        }).done(function (response) {
            if (response.max_count === true) {
                // Revert the checkbox state.
                $t.prop('checked', !$t.prop('checked'));
                formStyle.changeState($t);
            }
        });
    }
    project() {
        let $t = $(this), itemId = $t.closest('.item-container').data('id'), projId = $t.val();
        let action = $t.prop('checked') === true ? 'additem' : 'deleteitem';
        $.when(model.save({
            url: window.IL_BASE_URL + 'index.php/project/' + action,
            data: {
                id: itemId,
                project_id: projId
            }
        })).fail(function () {
            // Revert the checkbox state.
            $t.prop('checked', !$t.prop('checked'));
            formStyle.changeState($t);
        }).done(function (response) {
            if (response.max_count === true) {
                // Revert the checkbox state.
                $t.prop('checked', !$t.prop('checked'));
                formStyle.changeState($t);
            }
        });
    }
    /**
     * Only free version.
     */
    highlightSearch() {
        if (location.hash.indexOf('search_query') > -1 && $('.item-container').length > 0) {
            let uri = new URL('http://foo.bar/' + location.hash.substr(1));
            document.designMode = 'on';
            var sel = window.getSelection();
            for (let p of uri.searchParams) {
                if (p[0].indexOf('search_query') === 0) {
                    let terms = p[1].split(' ');
                    for (let t of terms) {
                        sel.collapse(document.getElementsByClassName('item-container')[0], 0);
                        t = t.replace('*', '');
                        if (t.length > 1) {
                            while (window.find(t, false)) {
                                document.execCommand('hiliteColor', false, '#ffff9bbf');
                                document.execCommand('foreColor', false, '#222426');
                            }
                        }
                    }
                }
            }
            sel.collapseToEnd();
            document.designMode = 'off';
            sel.removeAllRanges();
            $('#top-row').scrollTop(0);
        }
    }
}

views.itemsmain = new ItemsMainView();
views.itemsfilter = new ItemsMainView();

/**
 * Catalog.
 */
class ItemsCatalogView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'click .open-filter-local': views.itemsmain.filterLocal,
            'click .open-filter-remote': views.itemsmain.filterRemote,
            'click .clipboard': 'clipboard',
            'click .project': 'project',
            'click #open-export': exportform.init,
            'click #open-omnitool': omnitoolform.init
        };
    }
    afterRender(data) {
        views.itemsmain.afterRender(data);
    }
    clipboard() {
        let $t = $(this), itemId = $t.closest('.item-container').data('id');
        let action = $t.prop('checked') === true ? 'add' : 'delete';
        $.when(model.save({url: window.IL_BASE_URL + 'index.php/clipboard/' + action, data: {id: itemId}})).fail(function () {
            // Revert the checkbox state.
            $t.prop('checked', !$t.prop('checked'));
            formStyle.changeState($t);
        }).done(function (response) {
            if (response.max_count === true) {
                // Revert the checkbox state.
                $t.prop('checked', !$t.prop('checked'));
                formStyle.changeState($t);
            }
        });
    }
    project() {
        let $t = $(this), itemId = $t.closest('.item-container').data('id'), projId = $t.val();
        let action = $t.prop('checked') === true ? 'additem' : 'deleteitem';
        $.when(model.save({
            url: window.IL_BASE_URL + 'index.php/project/' + action,
            data: {
                id: itemId,
                project_id: projId
            }
        })).fail(function () {
            // Revert the checkbox state.
            $t.prop('checked', !$t.prop('checked'));
            formStyle.changeState($t);
        }).done(function (response) {
            if (response.max_count === true) {
                // Revert the checkbox state.
                $t.prop('checked', !$t.prop('checked'));
                formStyle.changeState($t);
            }
        });
    }
}

views.itemscatalog = new ItemsCatalogView();

/**
 * Clipboard.
 */
class ClipboardMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'click .open-filter-local': views.itemsmain.filterLocal,
            'click .open-filter-remote': views.itemsmain.filterRemote,
            'click .clipboard': 'clipboard',
            'click .project': 'project',
            'click #open-export': exportform.init,
            'click #open-omnitool': omnitoolform.init
        };
    }
    afterRender(data) {
        views.itemsmain.afterRender(data);
    }
    clipboard() {
        let $t = $(this), itemId = $t.closest('.item-container').data('id');
        let action = $t.prop('checked') === true ? 'add' : 'delete';
        $.when(model.save({url: window.IL_BASE_URL + 'index.php/clipboard/' + action, data: {id: itemId}})).done(function () {
            // Reload the page, because the list changed.
            B.history.loadUrl();
        }).fail(function () {
            // Revert the checkbox state.
            $t.prop('checked', !$t.prop('checked'));
            formStyle.changeState($t);
        });
    }
    project() {
        let $t = $(this), itemId = $t.closest('.item-container').data('id'), projId = $t.val();
        let action = $t.prop('checked') === true ? 'additem' : 'deleteitem';
        $.when(model.save({
            url: window.IL_BASE_URL + 'index.php/project/' + action,
            data: {
                id: itemId,
                project_id: projId
            }
        })).fail(function () {
            // Revert the checkbox state.
            $t.prop('checked', !$t.prop('checked'));
            formStyle.changeState($t);
        }).done(function (response) {
            if (response.max_count === true) {
                // Revert the checkbox state.
                $t.prop('checked', !$t.prop('checked'));
                formStyle.changeState($t);
            }
        });
    }
}

views.clipboardmain = new ClipboardMainView();
views.clipboardfilter = new ClipboardMainView();

/**
 * Projects view.
 */
class ProjectsMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit #project-form': 'submitForm',
            'click .join': 'joinProject',
            'click .leave': 'leaveProject',
            'click .activate': 'activateProject',
            'click .inactivate': 'inactivateProject'
        };
    }
    afterRender(data) {
        // Form beautification.
        formStyle.init();
        $('[data-toggle="tooltip"]').tooltip();
        $('.delete').confirmable({
            submit: function () {
                $.when(model.save({
                    url: window.IL_BASE_URL + 'index.php/project/delete',
                    data: {
                        project_id: $(this).data('projectId')
                    }
                })).done(function () {
                    B.history.loadUrl();
                });
            }
        });
        $('#filter-active').filterable({
            targets: '.active-project',
            complete: function () {
                $('.active-project-container').removeClass('d-none');
                $('.active-project.d-none').closest('.active-project-container').addClass('d-none');
            }
        });
        $('#filter-open').filterable({
            targets: '.open-project',
            complete: function () {
                $('.open-project-container').removeClass('d-none');
                $('.open-project.d-none').closest('.open-project-container').addClass('d-none');
            }
        });
        $('#filter-inactive').filterable({
            targets: '.inactive-project',
            complete: function () {
                $('.inactive-project-container').removeClass('d-none');
                $('.inactive-project.d-none').closest('.inactive-project-container').addClass('d-none');
            }
        });
    }
    submitForm(e) {
        let This = e.data.object;
        e.preventDefault();
        $.when(model.save({
            url: $(this).attr('action'),
            data: $(this).serialize()
        })).done(function (response) {
            $('#content-col').html(response.html);
            This.afterRender();
        });
    }
    joinProject() {
        $.when(model.save({
            url: window.IL_BASE_URL + 'index.php/project/join',
            data: {
                project_id: $(this).data('projectId')
            }
        })).done(function () {
            B.history.loadUrl();
        });
    }
    leaveProject() {
        $.when(model.save({
            url: window.IL_BASE_URL + 'index.php/project/leave',
            data: {
                project_id: $(this).data('projectId')
            }
        })).done(function () {
            B.history.loadUrl();
        });
    }
    activateProject() {
        $.when(model.save({
            url: window.IL_BASE_URL + 'index.php/project/activate',
            data: {
                project_id: $(this).data('projectId')
            }
        })).done(function () {
            B.history.loadUrl();
        });
    }
    inactivateProject() {
        $.when(model.save({
            url: window.IL_BASE_URL + 'index.php/project/inactivate',
            data: {
                project_id: $(this).data('projectId')
            }
        })).done(function () {
            B.history.loadUrl();
        });
    }
}

views.projectsmain = new ProjectsMainView();

class ProjectBrowseView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'click .open-filter-local': views.itemsmain.filterLocal,
            'click .open-filter-remote': views.itemsmain.filterRemote,
            'click .clipboard': 'clipboard',
            'click .project': 'project',
            'click #open-export': exportform.init,
            'click #open-omnitool': omnitoolform.init
        };
    }
    afterRender(data) {
        // Form beautification.
        formStyle.init();
        views.itemsmain.afterRender(data);
    }
    clipboard() {
        let $t = $(this), itemId = $t.closest('.item-container').data('id');
        let action = $t.prop('checked') === true ? 'add' : 'delete';
        $.when(model.save({url: window.IL_BASE_URL + 'index.php/clipboard/' + action, data: {id: itemId}})).fail(function () {
            // Revert the checkbox state.
            $t.prop('checked', !$t.prop('checked'));
            formStyle.changeState($t);
        });
    }
    project() {
        let $t = $(this), itemId = $t.closest('.item-container').data('id'), projId = $t.val();
        let action = $t.prop('checked') === true ? 'additem' : 'deleteitem';
        $.when(model.save({
            url: window.IL_BASE_URL + 'index.php/project/' + action,
            data: {
                id: itemId,
                project_id: projId
            }
        })).fail(function () {
            // Revert the checkbox state.
            $t.prop('checked', !$t.prop('checked'));
            formStyle.changeState($t);
        }).done(function (response) {
            if (response.max_count === true) {
                // Revert the checkbox state.
                $t.prop('checked', !$t.prop('checked'));
                formStyle.changeState($t);
            }
            B.history.loadUrl();
        });
    }
}

views.projectbrowse = new ProjectBrowseView();
views.projectfilter = new ProjectBrowseView();

class ProjectEditView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit #project-form': 'submitForm'
        };
    }
    afterRender(data) {
        // Form beautification.
        formStyle.init();
        $('#content-col').find('[data-toggle="tooltip"]').tooltip();
    }
    submitForm(e) {
        e.preventDefault();
        let $t = $(this);
        $.when(model.save({
            url: $t.attr('action'),
            data: $t.serialize()
        })).done(function () {
            B.history.loadUrl();
        });
    }
}

views.projectedit = new ProjectEditView();

class ProjectDiscussionView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit #message-form': 'bindForm'
        };
    }
    afterRender(data) {}
    bindForm(e) {
        e.preventDefault();
        let $f = $(this);
        $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function () {
            B.history.loadUrl();
        });
    }
}

views.projectdiscussion = new ProjectDiscussionView();

class ProjectNotesView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {};
    }
    afterRender(data) {}
}

views.projectnotes = new ProjectNotesView();
views.projectcompilenotes = new ProjectNotesView();

/**
 * Tools.
 */

class TagsManageView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit .edit-tag-form': 'editTag',
            'submit #tag-form': 'createTag'
        };
    }
    editTag(e) {
        e.preventDefault();
        let $f = $(this);
        $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function () {
            B.history.loadUrl();
        });
    }
    createTag(e) {
        e.preventDefault();
        let $f = $(this);
        $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function () {
            B.history.loadUrl();
        });
    }
}

views.tagsmanage = new TagsManageView();

class NormalizeMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'change #select-metadata': 'changeSource',
            'submit .edit-form': 'submitForm'
        };
    }
    afterRender(data) {
        let This = this, $in = $('#search-metadata');
        $in.filterable({
            source: $in.attr('data-source'),
            container: $in.attr('data-container')
        });
    }
    changeSource(e) {
        let This = e.data.object, $in = $('#search-metadata'), src = window.IL_BASE_URL + 'index.php/normalize/search' + $(this).val();
        $in.filterable('destroy');
        $('#results').empty();
        $in.attr('data-source', src).val('');
        $in.filterable({
            source: $in.attr('data-source'),
            container: $in.attr('data-container')
        });
    }
    submitForm(e) {
        e.preventDefault();
        let $f = $(this);
        $.when(model.save({url: $f.attr('action'), data: $f.serialize()}));
    }
}

views.normalizemain = new NormalizeMainView();

class NormalizeResultsView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit .edit-form': 'submitForm'
        };
    }
    submitForm(e) {
        e.preventDefault();
        let $f = $(this);
        $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function () {
            B.history.loadUrl();
        });
    }
}

views.normalizeauthors = new NormalizeResultsView();
views.normalizeeditors = new NormalizeResultsView();
views.normalizeprimary = new NormalizeResultsView();
views.normalizesecondary = new NormalizeResultsView();
views.normalizetertiary = new NormalizeResultsView();

/**
 * Admin tools.
 */
class DuplicatesMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
    }
}

views.duplicatesmain = new DuplicatesMainView();

class DuplicatesFindView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit .merge-form': 'merge'
        };
    }
    merge(e) {
        e.preventDefault();
        let $f = $(this);
        $.when(model.save({
            url: $f.attr('action'),
            data: $f.serialize()
        })).done(function () {
            $f.closest('.card').remove();
        });
    }
}

views.duplicatesfind = new DuplicatesFindView();

class ReindexMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'click #reindex': 'reindex',
            'click #defragment': 'defragment',
            'click #check-fts': 'integrityFts',
            'click #check-db':  'integrityDb',
            'click #reextract':  'reextract'
        };
    }
    integrityFts() {
        $.when(model.load({
            url: window.IL_BASE_URL + 'index.php/reindex/checkfts'
        }));
    }
    integrityDb() {
        $.when(model.load({
            url: window.IL_BASE_URL + 'index.php/reindex/checkdb'
        }));
    }
    defragment() {
        $.when(model.load({
            url: window.IL_BASE_URL + 'index.php/reindex/defragment'
        })).done(function () {
            B.history.loadUrl();
        });
    }
    reindex() {
        $.when(model.load({
            url: window.IL_BASE_URL + 'index.php/reindex/reindex'
        })).done(function () {
            B.history.loadUrl();
        });
    }
    reextract() {
        $.when(model.load({
            url: window.IL_BASE_URL + 'index.php/reindex/reextract'
        })).done(function () {
            B.history.loadUrl();
        });
    }
}

views.reindexmain = new ReindexMainView();

/**
 * Summary.
 */
class SummaryMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'click .clipboard': 'clipboard',
            'click .project': 'project',
            'click #open-export': exportform.init,
            'submit .form-uid': 'submitForm',
            'click .open-notes': 'openNotes',
            'click .rescan-pdf': 'rescanPdf'
        };
    }
    afterRender(data) {
        // Form beautification.
        formStyle.init();
        // Expandable lines.
        $('.truncate').expandable();
        let This = this;
        // Adjust height of top row.
        this.itemHeight();
        $(window).off('resize.SummaryMainView').on('resize.SummaryMainView', function () {
            This.itemHeight();
        });
        // Title list.
        if (typeof itemview === 'object') {
            itemview.clickTitle();
            itemview.changeTitleLink();
        }
        $('#delete-item').confirmable({
            submit: function () {
                $.when(model.save({url: window.IL_BASE_URL + 'index.php/item/delete', data: {id: $('body').data('id')}})).done(function () {
                    location.assign(window.IL_BASE_URL + 'index.php#dashboard/main');
                });
            }
        });
        // Add id to links, BODY, and notes.
        let params = (new URL('http://foo.bar/' + location.hash.substring(1))).searchParams,
            anId = params.get('id') || '';
        $('body').attr('data-id', anId).data('id', anId);
        $('a.add-id-link').each(function () {
            let thisHParts = $(this).attr('href').split('id=');
            $(this).attr('href', thisHParts[0] + 'id=' + anId);
        });
        // Navigation buttons. Id list: [{id:1,title:foo},{id=2,title:bar}]
        let itemList = sessionStore.load('il.idList');
        if (itemList) {
            let prevItem = itemList[itemList.indexOf(itemList.find(o => o.id === anId)) - 1],
                nextItem = itemList[itemList.indexOf(itemList.find(o => o.id === anId)) + 1];
            if (prevItem === undefined) {
                $('#summary-previous').addClass('disabled').attr('tabindex', '-1').attr('aria-disabled', 'true');
            } else {
                $('#summary-previous').attr('href', '#summary?id=' + prevItem['id']);
            }
            if (nextItem === undefined) {
                $('#summary-next').addClass('disabled').attr('tabindex', '-1').attr('aria-disabled', 'true');
            } else {
                $('#summary-next').attr('href', '#summary?id=' + nextItem['id']);
            }
        } else {
            $('#summary-next, #summary-previous').remove();
        }
        $('#autoupload-doi-form').saveable();
    }
    itemHeight() {
        let bH = $('#bottom-row').outerHeight();
        // Horizontal vs. vertical layout.
        if ($('.navbar-toggler').is(':visible')) {
            $('#top-row').height($(window).height() - bH - $('.navbar-toggler').outerHeight(true));
        } else {
            $('#top-row').height($(window).height() - bH);
        }
    }
    clipboard() {
        let $t = $(this), itemId = $('body').data('id');
        let action = $t.prop('checked') === true ? 'add' : 'delete';
        $.when(model.save({url: window.IL_BASE_URL + 'index.php/clipboard/' + action, data: {id: itemId}})).fail(function () {
            // Revert the checkbox state.
            $t.prop('checked', !$t.prop('checked'));
            formStyle.changeState($t);
        }).done(function (response) {
            if (response.max_count === true) {
                // Revert the checkbox state.
                $t.prop('checked', !$t.prop('checked'));
                formStyle.changeState($t);
            }
        });
    }
    project() {
        let $t = $(this), itemId = $('body').data('id'), projId = $t.val();
        let action = $t.prop('checked') === true ? 'additem' : 'deleteitem';
        $.when(model.save({
            url: window.IL_BASE_URL + 'index.php/project/' + action,
            data: {
                id: itemId,
                project_id: projId
            }
        })).fail(function () {
            // Revert the checkbox state.
            $t.prop('checked', !$t.prop('checked'));
            formStyle.changeState($t);
        }).done(function (response) {
            if (response.max_count === true) {
                // Revert the checkbox state.
                $t.prop('checked', !$t.prop('checked'));
                formStyle.changeState($t);
            }
        });
    }
    submitForm(e) {
        let $f = $(this);
        e.preventDefault();
        $.when(model.save({
            url: $f.attr('action'),
            data: $f.serialize()
        })).done(function () {
            B.history.loadUrl();
        });
        $('#autoupload-doi-form').trigger('save');
    }
    /**
     * Open notes. Init, or reload TinyMCE.
     */
    openNotes() {
        if (window.tinymce.activeEditor !== null && window.tinymce.activeEditor.initialized === true) {
            // Previously initialized, just show window.
            $('#notes-window').removeClass('d-none');
        } else {
            // Unintialized, load notes.
            views.summarymain.loadNotes();
        }
    }
    /**
     * Load textarea with item notes.
     */
    loadNotes() {
        let This = this, itemId = $('body').attr('data-id');
        $.when(model.load({url: window.IL_BASE_URL + 'index.php/notes/user', data: {id: itemId}})).done(function (response) {
            $('#notes-ta').val(response.user.note);
            $('#id-hidden').val(itemId);
            This.initNotes();
        });
    }
    initNotes() {
        if (window.tinymce.activeEditor !== null && window.tinymce.activeEditor.initialized === true) {
            window.tinymce.activeEditor.load();
            return;
        }
        window.tinymce.init({
            theme: 'silver',
            selector: '#notes-ta',
            content_css: window.IL_BASE_URL + "css/style.css",
            resize: 'both',
            min_height: 300,
            menubar: false,
            plugins: 'importcss save lists advlist link image code fullscreen table searchreplace',
            toolbar1: 'save undo redo fullscreen code | formatselect link unlink image table searchreplace',
            toolbar2: 'bold italic underline strikethrough subscript superscript removeformat | forecolor backcolor | outdent indent bullist numlist',
            save_onsavecallback: function (editor) {
                let $f = $('#note-form');
                $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function () {
                    $('#user-note').html(editor.getContent());
                    if (typeof window.MathJax.typeset === 'function') {
                        window.MathJax.typeset();
                    }
                });
            },
            image_description: false,
            relative_urls: false,
            remove_script_host: false,
            image_dimensions: false,
            image_class_list: [
                {title: 'Auto width', value: 'mce-img-fluid'}
            ],
            image_list: window.IL_BASE_URL + 'index.php/supplements/imagelist?as=json&id=' + $('body').data('id')
        }).then(function () {
            let $nw = $('#notes-window');
            $nw.removeClass('d-none');
            $nw.position({
                my: 'left bottom',
                at: 'left bottom',
                of: '#content-col'
            });
            $(window).off('resize.notes').on('resize.notes', function () {
                $nw.position({
                    my: 'left bottom',
                    at: 'left bottom',
                    of: '#content-col'
                });
            });
            // $(window).trigger('resize.notes');
            $nw.draggable({
                handle: ".card-header",
                containment: "body"
            });
            // Window close.
            $nw.find('.close').off('click.notes').on('click.notes', function () {
                $('#notes-window').addClass('d-none');
            });
        });
    }
    rescanPdf() {
        let link = $(this).data('url');
        $.when(model.load({url: link})).done(function () {
            B.history.loadUrl();
        });
    }
}

views.summarymain = new SummaryMainView();

/**
 * Notes.
 */
class NotesMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'click .open-notes': views.summarymain.openNotes
        };
    }
    afterRender() {
        // Add id to links, BODY, and notes.
        let params = (new URL('http://foo.bar/' + location.hash.substring(1))).searchParams,
            anId = params.get('id') || '';
        $('body').attr('data-id', anId).data('id', anId);
        // Title list.
        if (typeof itemview === 'object') {
            itemview.clickTitle();
            itemview.changeTitleLink();
        }
    }
}

views.notesmain = new NotesMainView();

/**
 * Edit item.
 */
class EditMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit #edit-form': 'bindForm',
            'change #reference-type': 'bindReferenceType'
        };
    }
    afterRender(data) {
        this.uploadFormWidgets();
        // Title list.
        if (typeof itemview === 'object') {
            itemview.clickTitle();
            itemview.changeTitleLink();
        }
    }
    bindForm(e) {
        e.preventDefault();
        let $f = $(this);
        $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function () {
            B.history.loadUrl();
        });
    }
    bindReferenceType() {
        $('#edit-form').trigger('submit');
    }
    uploadFormWidgets() {
        let This = this;
        $('input.input-typeahead').each(function () {
            let $t = $(this);
            $t.typeahead({
                source: $t.data('source'),
                onSelect: function () {
                    This._selectAuthor(this);
                }
            });
        });
        $('#clone-authors').clonable({
            target: '#new-author-container',
            onClone: function (e, data) {
                let $last = $(data.clonedTarget).find('.input-typeahead');
                $last.typeahead({
                    source: $last.data('source'),
                    onSelect: function () {
                        This._selectAuthor(this);
                    }
                });
            }
        });
        $('#clone-editors').clonable({
            target: '#new-editor-container',
            onClone: function (e, data) {
                let $last = $(data.clonedTarget).find('.input-typeahead');
                $last.typeahead({
                    source: $last.data('source'),
                    onSelect: function () {
                        This._selectAuthor(this);
                    }
                });
            }
        });
        $('#clone-uid').clonable({
            target: '#uid-row'
        });
    }
    _selectAuthor(typeahead) {
        if ($(typeahead).attr('name') === 'author_last_name[]') {
            let $first = $(typeahead).closest('.form-row').find('input[name="author_first_name\\[\\]"]');
            let names = $(typeahead).val().split(',');
            $(typeahead).val($.trim(names[0] || ''));
            $first.val($.trim(names[1] || ''));
        }
        if ($(typeahead).attr('name') === 'editor_last_name[]') {
            let $first = $(typeahead).closest('.form-row').find('input[name="editor_first_name\\[\\]"]');
            let names = $(typeahead).val().split(',');
            $(typeahead).val($.trim(names[0] || ''));
            $first.val($.trim(names[1] || ''));
        }
    }
}

views.editmain = new EditMainView();

/**
 * Item discussion.
 */
class ItemdiscussionMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.messagesId = '';
        this.events = {
            'submit #message-form': 'bindForm'
        };
    }
    afterRender(data) {
        // Messages timer.
        this.messagesId = setInterval(this.refreshMessages, 10000);
        // Title list.
        if (typeof itemview === 'object') {
            itemview.clickTitle();
            itemview.changeTitleLink();
        }
    }
    bindForm(e) {
        e.preventDefault();
        let This = e.data.object, $f = $(this);
        $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function () {
            This.refreshMessages();
            $f.get(0).reset();
        });
    }
    refreshMessages() {
        // Clear interval when user leaves the page view.
        if ($('#message-list').length === 0) {
            clearInterval(this.messagesId);
            return;
        }
        let itemId = $('body').data('id');
        $.when(model.load({url: window.IL_BASE_URL + 'index.php/itemdiscussion/messages?id=' + itemId})).done(function (response) {
            $('#message-list').html(response.html);
            $('#message').val('');
        });
    }
}

views.itemdiscussionmain = new ItemdiscussionMainView();

/**
 * Item tags.
 */
class TagsItemView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit #tag-form': 'createTags',
            'change .tag-inputs': 'changeTags'
        };
    }
    afterRender (data) {
        // Form beautification.
        formStyle.init();
        $('#tag-filter').filterable({
            complete: function () {
                $('#content-col .label-text').each(function() {
                    if($(this).hasClass('d-none')) {
                        $(this).parent().parent().addClass('d-none');
                    } else {
                        $(this).parent().parent().removeClass('d-none');
                    }
                });
                $('.tag-table').find('tr').each(function() {
                    if($(this).find('.label-text').length - $(this).find('.label-text.d-none').length === 0) {
                        $(this).addClass('d-none');
                    } else {
                        $(this).removeClass('d-none');
                    }
                });
            }
        });
        // Title list.
        if (typeof itemview === 'object') {
            itemview.clickTitle();
            itemview.changeTitleLink();
        }
    }
    /**
     * Create new item tags.
     */
    createTags(e) {
        e.preventDefault();
        let This = e.data.object, $f = $(this);
        if ($('#new_tags').val() === '') {
            return;
        }
        $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function (response) {
            $('#content-col').html(response.html);
            This.afterRender();
        });
    }
    /**
     * Tag checkboxes.
     */
    changeTags() {
        let $t = $(this), itemId = $('body').data('id');
        if ($t.is(':checked')) {
            // Add tag.
            $.when(model.save({
                url: window.IL_BASE_URL + 'index.php/tags/additem',
                data: {
                    id: itemId,
                    tag_id: $t.val()
                }
            })).fail(function () {
                // Revert change on fail.
                $t.prop('checked', false);
                formStyle.updateStyle($t);
            });
        } else {
            // Remove tag.
            $.when(model.save({
                url: window.IL_BASE_URL + 'index.php/tags/deleteitem',
                data: {
                    id: itemId,
                    tag_id: $t.val()
                }
            })).fail(function () {
                // Revert change on fail.
                $t.prop('checked', true);
                formStyle.updateStyle($t);
            });
        }
    }
}

views.tagsitem = new TagsItemView();

/**
 * Supplementary files.
 */
class SupplementsMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'click .rename-file': 'renameFile',
            'keydown input.form-control': 'submitInput'
        };
    }
    afterRender(data) {
        formStyle.init();
        // File upload form.
        $('#upload-form').uploadable({
            maxFiles: 64
        });
        // Delete file button.
        $('.delete-file').confirmable({
            submit: function () {
                let file = $(this).closest('li').find('.filename-link').text().trim(),
                    itemId = $('body').data('id');
                $.when(model.save({
                    url: window.IL_BASE_URL + 'index.php/supplements/delete',
                    data: {
                        id: itemId,
                        filename: file
                    }
                })).done(function () {
                    B.history.loadUrl();
                });
            }
        });
        // Title list.
        if (typeof itemview === 'object') {
            itemview.clickTitle();
            itemview.changeTitleLink();
        }
    }
    renameFile() {
        let $l = $(this).closest('li').find('.filename-link'),
            $i = $(this).closest('li').find('input');
        if ($l.hasClass('d-none')) {
            // Submit.
            let filename = $.trim($l.text()), newname = $.trim($i.val());
            $.when(model.save({
                url: window.IL_BASE_URL + 'index.php/supplements/rename',
                data: {
                    id: $('body').data('id'),
                    filename: filename,
                    newname: newname
                }
            })).done(function () {
                B.history.loadUrl();
            });
        } else {
            // Show input.
            $l.addClass('d-none');
            $i.removeClass('d-none').addClass('d-inline-block').val($.trim($l.text())).focus();
        }
    }
    /**
     * Submit the rename input with Enter key.
     * @param {object} e Event.
     */
    submitInput(e) {
        if (e.which === 13) {
            $(this).closest('.list-group-item').find('.rename-file').trigger('click');
        }
    }
}

views.supplementsmain = new SupplementsMainView();

/**
 * PDF.
 */
class PdfMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'change #pdfviewer-zoom':      'selectZoom',
            'input #pdfviewer-page-input': 'inputPageNum',
            'click #pdfviewer-first':      'buttonPageNum',
            'click #pdfviewer-prev':       'buttonPageNum',
            'click #pdfviewer-next':       'buttonPageNum',
            'click #pdfviewer-last':       'buttonPageNum',
            'click .pdfviewer-thumb':      'thumbPageNum',
            'click #pdfviewer-image':      'toggleCropper',
            'click #copy-image-btn':       'getCroppedImage',
            'click #save-image-btn':       'saveCroppedImage',
            'click #pdfviewer-left-btn':   'toggleLeft',
            'click #pdfviewer-previews-btn': 'showThumbs',
            'click #pdfviewer-bookmarks-btn': 'showBookmarks',
            'click #pdfviewer-results-btn': 'showResults',
            'click #pdfviewer-bookmarks a': 'clickBookmark',
            'click #pdfviewer-night-btn':  'nightMode',
            'click #pdfviewer-text-btn':  'toggleTextLayer',
            'click .highlight-color':      'addHighlights',
            'click .highlight-eraser':     'eraserInit',
            'click #pdfviewer-annot-menu .annot-show': 'showAnnotations',
            'click #pdfviewer-annot-menu .annot-hide': 'hideAnnotations',
            'click .highlight-cancel':     'clearHighlights',
            'dblclick .pdfviewer-page':    'dblZoom',
            'keyup #pdfviewer-search-input':'search',
            'click #pdfviewer-results > .pdfviewer-results-container > a': 'scrollToResult',
            'click #pdfviewer-result-up': 'prevResult',
            'click #pdfviewer-result-down': 'nextResult',
            'click #pdfviewer-notes-btn':  'showNotes',
            'click .note-edit-btn': 'editNote',
            'submit .note-form': 'saveNote',
            'submit #new-note-form': 'saveNote',
            'click #pdfviewer-new-note-btn': 'createNewNote',
            'click .pdflink': 'openLink',
            'change #pdfviewer-underlined': 'highlightStyle'
        };
    }
    afterRender(data) {
        this.selectable = undefined;
        this.page = undefined;
        this.sseSearch = undefined;
        let This = this;
        this.throttledZoom = _.throttle(function (zoom) {
            This.pageZoom(zoom);
        }, 500, {leading: true, trailing: true});
        // Title list.
        if (typeof itemview === 'object') {
            itemview.clickTitle();
            itemview.changeTitleLink();
        }
        // No PDF.
        if ($('#pdfviewer-pages').length === 0) {
            return;
        }
        // Underlined checkbox.
        if (store.load('il.highlightStyle') === 'underlined') {
            $('#pdfviewer-underlined').prop('checked', true);
        }
        formStyle.init();
        $('#content-col').find('[data-toggle="tooltip"]').tooltip();
        // Id in body tag for HTML view.
        let urlParams = (new URL(location.href)).searchParams,
            anId = urlParams.get('id') || '';
        if (anId !== '') {
            $('body').attr('data-id', anId).data('id', anId);
        }
        // Update page zoom on resize.
        $(window).off('resize.PdfMainView').on('resize.PdfMainView', function () {
            This.pagesHeight();
            This.pageZoom(store.load('il.pageZoom') || 'auto');
            // Page change detection.
            $('.pdfviewer-right').trigger('scroll');
        });
        // Set page container height.
        this.pagesHeight();
        // Lazy load images.
        new LazyLoad({
            container: $('#pdfviewer-pages .pdfviewer-left').get(0),
            elements_selector: '.lazy',
            load_delay: 250,
            threshold: 400
        });
        this.lazyLoad = new LazyLoad({
            container: document.querySelector('.pdfviewer-right'),
            elements_selector: '.lazy',
            load_delay: 250,
            threshold: 1000
        });
        // Initial night mode.
        if (store.load('il.nightMode') === true) {
            $('#pdfviewer-night-btn').click();
        }
        // Initial left panel.
        if (store.load('il.leftPanel') === true) {
            this.showLeft();
            this.showThumbs();
        }
        // Set initial page zoom.
        this.pageZoom(store.load('il.pageZoom') || 'auto');
        // Initial page sharpening on Webkit.
        $('#adjust-sharpness').val(imageFilter.default.sharpness);
        // Page change detection on scroll stop.
        $('.pdfviewer-right').off('scroll').on('scroll', _.throttle(function () {
            This.redrawNoteLine();
            This.redrawSnippetLine();
            // Disable sharpening when scrolling.
            imageFilter.disableSharpening(This.page);
            clearTimeout($.data(window, 'scrollTimer'));
            $.data(window, 'scrollTimer', setTimeout(function () {
                $('.pdfviewer-right > div').each(function () {
                    return !This.detectVisiblePage(
                        this,
                        this.getBoundingClientRect(),
                        $('.pdfviewer-right')[0].getBoundingClientRect()
                    );
                });
                // Refresh text box coordinates.
                if (typeof This.selectable === 'object') {
                    This.selectable.refresh();
                }
                imageFilter.adjustSharpness(This.page);
            }, 200));
        }, 20));
        $('.pdfviewer-left').off('scroll').on('scroll', _.throttle(function () {
            This.redrawNoteLine();
            This.redrawSnippetLine();
        }, 20));
        if (typeof This.selectable === 'object') {
            This.selectable.disable();
            This.selectable.destroy();
        }
        if (typeof This.cropper === 'object') {
            This.destroyCropper();
        }
        // Search param.
        let params = {}, e = $.Event("keyup");
        if (location.hash.length > 1) {
            params = (new URL('http://foo.bar/' + location.hash.substring(1))).searchParams;
        } else {
            params = (new URL(location.href)).searchParams;
        }
        if (params.get('search') !== null) {
            e.which = 13;
            $('#pdfviewer-search-input').val(params.get('search')).trigger(e);
        }
        // Initial page number.
        let initialPage = params.get('page') || $('#pdfviewer-page-input').val();
        this.setPageNumber(initialPage);
        this.scrollToPage(initialPage, 0);
        this.getLinks();
    }
    /**
     * Zoom on select change.
     */
    selectZoom(e) {
        e.data.object.throttledZoom($(this).val());
    }
    /**
     * Double-click zoom toggle.
     */
    dblZoom(e) {
        let zoom = store.load('il.pageZoom');
        if (zoom === 'auto') {
            let pageWidth = $('.pdfviewer-right').width() - 30,
                imgWidth = 0.5 * $('.pdfviewer-page > img').eq(0).attr('width'),
                tempZoom = 100 * pageWidth / imgWidth;
            [50, 75, 100, 125, 150, 200, 250, 300].forEach(function(v) {
                if (v <= tempZoom) {
                    zoom = v.toString();
                }
            });
        } else {
            zoom = 'auto';
        }
        e.data.object.pageZoom(zoom);
    }
    /**
     * Execute page zoom change.
     * @param {number|string} zoom
     */
    pageZoom(zoom) {
        if (zoom === 'screen') {
            zoom = 'auto';
        }
        let pageBefore = this.page, imgZoom;
        $('#pdfviewer-zoom').val(zoom);
        store.save('il.pageZoom', zoom);
        // Set image zoom factor.
        if (zoom === 'auto') {
            let pageWidth = $('.pdfviewer-right').width() - 30,
                imgWidth = $('.pdfviewer-page > img').eq(0).attr('width'),
                tempZoom = Math.max(100 * pageWidth / imgWidth, 50);
            [50, 75, 100, 125, 150, 200, 250, 300].forEach(function(v) {
                if (v <= tempZoom) {
                    zoom = v.toString();
                }
            });
        }
        switch(zoom) {
            case '50':
            case '100':
            case '200':
                imgZoom = '200';
                break;
            case '125':
            case '250':
                imgZoom = '250';
                break;
            case '75':
            case '150':
            case '300':
                imgZoom = '300';
                break;
            default:
                imgZoom = '200';
        }
        $('.pdfviewer-page > img').each(function () {
            // Resize images.
            this.style.width  = Math.ceil(0.01 * zoom * this.getAttribute('width')) + 'px';
            this.style.height = Math.ceil(0.01 * zoom * this.getAttribute('height')) + 'px';
            // Reset lazy loading.
            if (this.getAttribute('data-src') !== null) {
                this.removeAttribute('data-was-processed');
                this.classList.remove('loaded');
                this.setAttribute('data-src', this.getAttribute('data-src').replace(/zoom=\d+/, 'zoom=' + imgZoom));
            }
        });
        this.lazyLoad.update();
        this.scrollToPage(pageBefore, 0);
    }
    /**
     * Set correct page container height.
     */
    pagesHeight() {
        let mH = $('#pdfviewer-menu').outerHeight();
        // Horizontal vs. vertical layout.
        if ($('.navbar-toggler').is(':visible')) {
            $('#pdfviewer-pages, .pdfviewer-left, .pdfviewer-right').height($(window).height()
                - mH
                - $('.navbar-toggler').outerHeight(true));
        } else {
            $('#pdfviewer-pages, .pdfviewer-left, .pdfviewer-right').height($(window).height() - mH);
        }
    }
    /**
     * Get the page in viewport.
     */
    detectVisiblePage(el, elRect, rootRect) {
        if ((elRect.top - rootRect.top) > 0 && (elRect.top - rootRect.top) < (rootRect.height / 2)) {
            // Image top is above 50% line.
            this.setPageNumber($(el).data('page'));
            return true;
        } else
        if ((rootRect.bottom - elRect.bottom) > 0 && (rootRect.bottom - elRect.bottom) < (rootRect.height / 2)) {
            // Image bottom is below 50% line.
            this.setPageNumber($(el).data('page'));
            return true;
        } else
        if ((rootRect.top - elRect.top) > 0 && (elRect.bottom - rootRect.bottom) > 0) {
            // Image covers all viewport.
            this.setPageNumber($(el).data('page'));
            return true;
        }
        return false;
    }
    /**
     * Execute page change.
     * @param {number} pgNum
     */
    setPageNumber(pgNum) {
        let This = this;
        // If page the same exit.
        if (This.page === parseInt(pgNum)) {
            return;
        }
        // Page property.
        This.page = parseInt(pgNum);
        // Use delay, to prevent rapid firing.
        if (typeof this.pageTimer !== 'undefined') {
            clearTimeout(this.pageTimer);
        }
        this.pageTimer = _.delay(function() {
            $('#pdfviewer-page-input').val(pgNum);
            // Update thumbs.
            $('.pdfviewer-thumb').children('div').removeClass('bg-primary').addClass('bg-secondary');
            $('.pdfviewer-thumb').eq(pgNum - 1).children('div').removeClass('bg-secondary').addClass('bg-primary');
            $('#pdfviewer-pages .pdfviewer-left').animate({
                scrollTop: $('.pdfviewer-thumb').eq(pgNum - 1).position().top +
                    $('#pdfviewer-pages .pdfviewer-left').scrollTop() - ($('.pdfviewer-thumb').eq(pgNum - 1).height() / 2)
            }, 100);
            // Layers.
            if($('#pdfviewer-pages').find('.pdfviewer-text').length > 0) {
                This.getBoxes(pgNum);
            }
            if ($('#pdfviewer-pages').find('.pdfviewer-highlights').length > 0) {
                This.getHighlights();
            }
            // Save to logs.
            model.load({
                url: window.IL_BASE_URL + 'index.php/pdf/logpage',
                data: {
                    id: $('body').data('id'),
                    page: pgNum
                },
                async: true
            });
        }, 400);
    }
    /**
     * Change page number on button click.
     */
    buttonPageNum(e) {
        let i, pgNum, speed = 600;
        switch ($(this).data('value')) {
            case 'first':
                pgNum = 1;
                break;
            case 'prev':
                pgNum = parseInt($('#pdfviewer-page-input').val());
                for (i = pgNum; i > pgNum - 10; i--) {
                    let elRect = $('.pdfviewer-page').eq(i - 1)[0].getBoundingClientRect(),
                        prevRect = $('.pdfviewer-page').eq(i - 2)[0].getBoundingClientRect();
                    if (elRect.top > prevRect.top) {
                        pgNum = i - 1;
                        break;
                    }
                }
                speed = 200;
                break;
            case 'next':
                pgNum = parseInt($('#pdfviewer-page-input').val());
                for (i = pgNum; i < pgNum + 10; i++) {
                    let elRect = $('.pdfviewer-page').eq(i - 1)[0].getBoundingClientRect(),
                        nextRect = $('.pdfviewer-page').eq(i)[0].getBoundingClientRect();
                    if (nextRect.top > elRect.top) {
                        pgNum = i + 1;
                        break;
                    }
                }
                speed = 200;
                break;
            case 'last':
                pgNum = $('.pdfviewer-page').last().data('page');
                break;
        }
        e.data.object.scrollToPage(pgNum, speed);
    }
    /**
     * Change page number on input change.
     */
    inputPageNum(e) {
        let This = e.data.object, pg = $(this).val();
        if (typeof This.t !== 'undefined') {
            clearTimeout(This.t);
        }
        This.t = _.delay(function() {
            This.scrollToPage(pg);
        }, 300);
    }
    thumbPageNum(e) {
        e.data.object.scrollToPage($(this).data('page'));
    }
    /**
     * Scroll to a page number.
     * @param {number} pgNum
     * @param {number|undefined} speed
     */
    scrollToPage(pgNum, speed = undefined) {
        let time = typeof speed === 'undefined' ? 400 : speed;
        if ($('.pdfviewer-page[data-page="' + pgNum + '"]').length === 1) {
            $('.pdfviewer-right').animate({
                scrollTop: $('.pdfviewer-page[data-page="' + pgNum + '"]').position().top +
                    $('.pdfviewer-right').scrollTop() - 12
            }, time);
        }
    }
    scrollToElement(el, speed) {
        let time = typeof speed === 'undefined' ? 400 : speed, $pr = $('.pdfviewer-right'), elTop = el.offset().top;
        if (elTop < 100 || elTop > $(window).height() - 100) {
            $pr.animate({
                scrollTop: elTop + $pr.scrollTop() - ($(window).height() / 2)
            }, {
                duration: time,
                queue: false
            });
        }
        let elRect = el[0].getBoundingClientRect(), contRect = $pr[0].getBoundingClientRect();
        if (elRect.left < contRect.left || elRect.right > contRect.right) {
            $pr.animate({
                scrollLeft: $pr.scrollLeft() + elRect.left - contRect.left - 0.5 * $pr.width()
            }, {
                duration: time,
                queue: false
            });
        }
    }
    toggleCropper(e) {
        if (typeof e.data.object.cropper === 'undefined') {
            e.data.object.clearHighlights(e);
            e.data.object.clearNotes(e);
            e.data.object.pageZoom('auto');
            e.data.object.destroyTextLayer();
            // Disable other controls.
            $('.dropdown.show .dropdown-toggle').dropdown('toggle');
            $('#pdfviewer-menu').find('button, input, select').prop('disabled', true);
            $('.btn-group-toggle').addClass('disabled').children().addClass('disabled');
            $('#pdfviewer-image').prop('disabled', false);
            let imageIndex = e.data.object.page - 1;
            e.data.object.cropper = new Cropper($('.pdfviewer-page > img')[imageIndex], {
                background: false,
                viewMode: 1,
                dragMode: 'move',
                initialAspectRatio: 1,
                rotatable: false,
                movable: false,
                zoomable: false,
                autoCropArea: 0.5,
                ready: function () {
                    // Add Copy and Save buttons.
                    $('.cropper-crop-box')
                        .append('<button id="copy-image-btn">Copy</button>')
                        .append('<button id="save-image-btn">Save</button>');
                    $('#copy-image-btn').addClass('btn btn-primary').css({
                        position: 'absolute',
                        bottom: '-36px',
                        left: 0,
                        'font-size': '15px',
                        'line-height': '15px'
                    });
                    $('#save-image-btn').addClass('btn btn-danger').css({
                        position: 'absolute',
                        bottom: '-36px',
                        left: '74px',
                        'font-size': '15px',
                        'line-height': '15px'
                    });
                }
            });
            // Remove night mode.
            if ($('#pdfviewer-pages > .pdfviewer-right > .pdfviewer-page:first').hasClass('img-night-mode')) {
                e.data.object.nightMode();
            }
        } else {
            e.data.object.destroyCropper();
        }
    }
    destroyCropper() {
        // Enable other controls.
        $('#pdfviewer-menu').find('button, input, select').prop('disabled', false);
        $('.btn-group-toggle').removeClass('disabled').children().removeClass('disabled');
        if (typeof this.cropper === 'object') {
            this.cropper.destroy();
            this.cropper = undefined;
        }
    }
    getCroppedImage(e) {
        let This = e.data.object, data = This.cropper.getData(true);
        _.assign(data, {id: $('body').data('id'), page: This.page});
        location.assign(window.IL_BASE_URL + 'index.php/page/loadcrop?' + $.param(data));
    }
    saveCroppedImage(e) {
        let This = e.data.object, data = This.cropper.getData(true);
        _.assign(data, {id: $('body').data('id'), page: This.page});
        model.save({
            url: window.IL_BASE_URL + 'index.php/page/savecrop',
            data: $.param(data)
        });
    }
    /**
     * Toggle left pane.
     */
    toggleLeft(e) {
        let This = typeof e === 'object' ? e.data.object : this;
        if ($('.pdfviewer-left').hasClass('d-none')) {
            This.showLeft();
        } else {
            This.hideLeft();
        }
    }
    showLeft() {
        $('.pdfviewer-left').removeClass('d-none');
        $(window).trigger('resize.PdfMainView');
        store.save('il.leftPanel', $('.pdfviewer-left').is(':visible'));
        if ($('#pdfviewer-thumbs').hasClass('d-none') === true &&
            $('#pdfviewer-bookmarks').hasClass('d-none') === true &&
            $('#pdfviewer-notes').hasClass('d-none') === true &&
            $('#pdfviewer-results').hasClass('d-none') === true) {
            this.showThumbs();
        }
    }
    hideLeft() {
        $('.pdfviewer-left').addClass('d-none');
        $(window).trigger('resize.PdfMainView');
        store.save('il.leftPanel', $('.pdfviewer-left').is(':visible'));
    }
    showThumbs(e) {
        let This = typeof e === 'object' ? e.data.object : this;
        $('#pdfviewer-thumbs').removeClass('d-none');
        $('#pdfviewer-bookmarks').addClass('d-none');
        $('#pdfviewer-notes').addClass('d-none');
        $('#pdfviewer-results').addClass('d-none');
        This.redrawNoteLine();
        This.redrawSnippetLine();
        let pageWidth = 230;
        $('.pdfviewer-thumb > img').each(function () {
            if (this.style.width === '230px') {
                return;
            }
            // Image size.
            this.style.width  = pageWidth + 'px';
            this.style.height = (pageWidth / this.getAttribute('width')) * this.getAttribute('height') + 'px';
        });
    }
    showBookmarks(e) {
        let This = typeof e === 'object' ? e.data.object : this;
        if ($('#pdfviewer-bookmarks :text').length === 1) {
            $('#pdfviewer-thumbs').addClass('d-none');
            $('#pdfviewer-bookmarks').removeClass('d-none');
            $('#pdfviewer-notes').addClass('d-none');
            $('#pdfviewer-results').addClass('d-none');
            This.redrawNoteLine();
            This.redrawSnippetLine();
            return false;
        }
        $.when(model.load({url: window.IL_BASE_URL + 'index.php/pdf/bookmarks?id=' + $('body').data('id')})).done(function (response) {
            $('#pdfviewer-thumbs').addClass('d-none');
            $('#pdfviewer-notes').addClass('d-none');
            $('#pdfviewer-results').addClass('d-none');
            $('#pdfviewer-bookmarks').removeClass('d-none').html(response.html);
            $('#pdfviewer-bookmarks :text').filterable({targets: '#pdfviewer-bookmarks a'}).val('');
            This.redrawNoteLine();
            This.redrawSnippetLine();
        });
    }
    clickBookmark(e) {
        e.preventDefault();
        e.data.object.scrollToPage($(this).data('page'));
    }
    nightMode() {
        $('#pdfviewer-pages > .pdfviewer-right > .pdfviewer-page').toggleClass('img-night-mode img-light-mode');
        let nightMode = $('#pdfviewer-pages > .pdfviewer-right > .pdfviewer-page:first').hasClass('img-night-mode');
        if (nightMode) {
            imageFilter.nightMode();
        } else {
            imageFilter.lightMode();
        }
        store.save('il.nightMode', nightMode);
    }
    toggleTextLayer(e) {
        let This = e.data.object, pgNum = This.page;
        if ($('#pdfviewer-pages').find('.pdfviewer-text').length === 0) {
            This.clearHighlights(e);
            This.clearNotes(e);
            // Init Selectable.
            if (typeof This.selectable === 'undefined') {
                This.selectable = new Selectable({
                    lasso: {
                        backgroundColor: "transparent"
                    },
                    lassoSelect: "sequential",
                    appendTo: "#pdfviewer-pages",
                    maxSelectable: 1000
                });
            }
            This.selectable.off('end');
            This.selectable.on('end', This.copyText);
            This.getBoxes(pgNum);
        } else {
            // Destroy Selectable.
            This.destroyTextLayer();
        }
    }
    destroyTextLayer() {
        $('#pdfviewer-pages').find('.pdfviewer-text').remove();
        // Destroy Selectable.
        if (typeof this.selectable === 'object') {
            this.selectable.off('end', this.copyText);
            this.selectable.disable();
            this.selectable.destroy();
            this.selectable = undefined;
        }
    }
    copyText() {
        let txt = '';
        $(".pdfviewer-text").children(".ui-selected").each(function () {
            txt = txt + $(this).attr('data-text') + ' ';
        });
        // Try to remove word hyphenation.
        txt = txt.replace(/(- )/g, '');
        txt = txt.replace(/\s{2,}/g, ' ');
        txt = $.trim(txt);
        if (txt === '') {
            return false;
        }
        // Insert text to textarea.
        $('<textarea id="copy-text-container" style="position: fixed;left: -1000px">'
                + txt + '</textarea>').appendTo('body');
        $('#copy-text-container').select();
        try {
            // Copy text to clipboard.
            document.execCommand('copy');
            $('#copy-text-container').remove();
        } catch (err) {
            // Failed, open dialog to copy text manually.
            $.jGrowl('Copied to clipboard not supported by this browser.');
            return false;
        }
        $.jGrowl('<div class="text-truncate">' + txt + '</div>', {
            header: 'Copied to clipboard',
            theme: 'bg-primary'
        });
        this.clear();
    }
    getHighlights() {
        $.when(model.load({
            url: window.IL_BASE_URL + 'index.php/pdf/highlights',
            data: {
                id: $('body').data('id')
            },
            async: true
        })).done(function (response) {
            $('#pdfviewer-pages').find('.pdfviewer-highlights').remove();
            let underlinedClass = '';
            if (store.load('il.highlightStyle') === 'underlined') {
                underlinedClass = 'underlined';
            }
            _.forEach(response.highlights, function (boxes, page) {
                let $con = $('#pdfviewer-pages').find('.pdfviewer-page').eq(page - 1);
                $(boxes).addClass(underlinedClass).insertAfter($con.children('img'));
            });
        });
    }
    showAnnotations (e) {
        let This = e.data.object;
        if ($('#pdfviewer-pages').find('.pdfviewer-highlights').length === 0) {
            This.getHighlights();
        }
        This.showNotes(e);
    }
    hideAnnotations (e) {
        let This = e.data.object;
        This.clearHighlights(e);
        This.clearNotes(e);
    }
    addHighlights(e) {
        let This = e.data.object, pgNum = This.page;
        This.clearNotes(e);
        if ($(this).hasClass('highlight-blue')) {
            store.save('il.highlight', 'B');
            $('#pdfviewer-pages').find('.pdfviewer-page').removeClass(function (i, c) {
                return (c.match (/(^|\s)cursor-\S+/g) || []).join(' ');
            }).addClass('cursor-marker-blue');
        } else if ($(this).hasClass('highlight-yellow')) {
            store.save('il.highlight', 'Y');
            $('#pdfviewer-pages').find('.pdfviewer-page').removeClass(function (i, c) {
                return (c.match (/(^|\s)cursor-\S+/g) || []).join(' ');
            }).addClass('cursor-marker-yellow');
        } else if ($(this).hasClass('highlight-green')) {
            store.save('il.highlight', 'G');
            $('#pdfviewer-pages').find('.pdfviewer-page').removeClass(function (i, c) {
                return (c.match (/(^|\s)cursor-\S+/g) || []).join(' ');
            }).addClass('cursor-marker-green');
        } else if ($(this).hasClass('highlight-red')) {
            store.save('il.highlight', 'R');
            $('#pdfviewer-pages').find('.pdfviewer-page').removeClass(function (i, c) {
                return (c.match (/(^|\s)cursor-\S+/g) || []).join(' ');
            }).addClass('cursor-marker-red');
        }
        if ($('#pdfviewer-pages').find('.pdfviewer-highlights').length === 0) {
            This.getHighlights();
        }
        // Init Selectable.
        if (typeof This.selectable === 'undefined') {
            This.selectable = new Selectable({
                lasso: {
                    backgroundColor: "transparent"
                },
                lassoSelect: "sequential",
                appendTo: "#pdfviewer-pages",
                maxSelectable: 1000
            });
        }
        This.selectable.off('end');
        This.selectable.on('end', This.saveHighlights);
        if ($('#pdfviewer-pages').find('.pdfviewer-text').length === 0) {
            This.getBoxes(pgNum);
        }
    }
    saveHighlights(e, selected) {
        let This = window.pdfmainview || views.pdfmain, b = {}, colClass, col = store.load('il.highlight');
        // Color class.
        switch (col) {
            case 'R':
                colClass = 'red';
                break;
            case 'G':
                colClass = 'green';
                break;
            case 'B':
                colClass = 'blue';
                break;
            case 'Y':
                colClass = 'yellow';
                break;
        }
        _.forEach(selected, function (o, i) {
            let t, l, w, h, $t = $(o.node), $cont = $t.parent().siblings('.pdfviewer-highlights'), styles = $t.attr('style').split(';');
            // Clone divs to the highlight div.
            $t.clone().removeClass('ui-selectable ui-selected').addClass(colClass).appendTo($cont);
            _.forEach(styles, function (style) {
                let parts = style.split(':');
                switch (parts[0]) {
                    case 'top':
                        t = 10 * parseFloat(parts[1].slice(0, -1));
                        break;
                    case 'left':
                        l = 10 * parseFloat(parts[1].slice(0, -1));
                        break;
                    case 'width':
                        w = 10 * parseFloat(parts[1].slice(0, -1));
                        break;
                    case 'height':
                        h = 10 * parseFloat(parts[1].slice(0, -1));
                        break;
                }
            });
            b[i] = {
                page: $t.closest('.pdfviewer-page').data('page'),
                top: t,
                left: l,
                width: w,
                height: h,
                position: $t.data('position'),
                text: $t.attr('data-text')
            };
        });
        if (typeof b[0] === 'object') {
            // Save boxes to server.
            $.when(model.save({
                url: window.IL_BASE_URL + 'index.php/pdf/savehighlights',
                data: {
                    id:    $('body').data('id'),
                    color: col,
                    boxes: JSON.stringify(b)
                },
                async: true
            })).done(function () {
                This.getHighlights();
            });
        }
    }
    clearHighlights(e) {
        let This = e.data.object, $pages = $('#pdfviewer-pages');
        $pages.find('.pdfviewer-text').remove();
        store.delete('il.highlight');
        // Destroy Selectable.
        if (typeof This.selectable === 'object') {
            This.selectable.off('end', This.saveHighlights);
            This.selectable.destroy();
            This.selectable = undefined;
        }
        $pages.find('.pdfviewer-page').removeClass(function (i, c) {
            return (c.match (/(^|\s)cursor-\S+/g) || []).join(' ');
        });
        $pages.find('.pdfviewer-highlights').remove();
    }
    eraserInit(e) {
        let This = e.data.object, $pages = $('#pdfviewer-pages');
        if ($pages.find('.pdfviewer-highlights').length > 0) {
            // Make sure we have selectable.
            if (typeof This.selectable === 'undefined') {
                This.selectable = new Selectable({
                    lasso: {
                        backgroundColor: "transparent"
                    },
                    lassoSelect: "sequential",
                    appendTo: "#pdfviewer-pages",
                    maxSelectable: 1000
                });
            }
            This.selectable.off('end');
            This.selectable.on('end', This.deleteHighlights);
            // Make sure we have selectable boxes.
            if ($pages.find('.pdfviewer-text').length === 0) {
                This.getBoxes(This.page);
            }
            $pages.find('.pdfviewer-page').addClass('cursor-eraser');
        }
    }
    deleteHighlights(e, selected) {
        let This = window.pdfmainview || views.pdfmain, b = {};
        _.forEach(selected, function (o, i) {
            let $t = $(o.node), $cont = $t.parent().siblings('.pdfviewer-highlights'), pos = $t.data('position');
            // Delete divs in the highlight div.
            $cont.children('[data-position="' + pos + '"]').remove();
            b[i] = {
                page: $t.closest('.pdfviewer-page').data('page'),
                position: pos
            };
        });
        if (typeof b[0] === 'object') {
            $.when(model.save({
                url: window.IL_BASE_URL + 'index.php/pdf/deletehighlights',
                data: {
                    id:    $('body').data('id'),
                    boxes: JSON.stringify(b)
                },
                async: true
            })).done(function () {
                This.getHighlights()
            });
        }
    }
    highlightStyle() {
        // Save setting.
        if ($(this).prop('checked') === true) {
            $('#pdfviewer-pages').find('.pdfviewer-highlights').addClass('underlined');
            store.save('il.highlightStyle', 'underlined');
        } else {
            $('#pdfviewer-pages').find('.pdfviewer-highlights').removeClass('underlined');
            store.save('il.highlightStyle', 'highlight');
        }
    }
    getLinks() {
        let sseLinks = new EventSource(IL_BASE_URL + 'index.php/pdf/links?id=' + $('body').data('id'));
        sseLinks.onmessage = function(e) {
            if (e.data === 'CLOSE' || $('#pdfviewer-pages').length === 0) {
                sseLinks.close();
            } else {
                let data = JSON.parse(e.data);
                _.forEach(data, function (div, page) {
                    let $con = $('#pdfviewer-pages').find('.pdfviewer-page').eq(page - 1);
                    $con.append(div);
                });
            }
        }
    }
    getBoxes(pgNum) {
        let This = this;
        $.when(model.load({
            url: window.IL_BASE_URL + 'index.php/pdf/boxes',
            data: {
                id: $('body').data('id'),
                page: pgNum
            },
            async: true
        })).done(function (response) {
            $('#pdfviewer-pages').find('.pdfviewer-text').remove();
            _.forEach(response.boxes, function (boxes, page) {
                let $con = $('#pdfviewer-pages').find('.pdfviewer-page').eq(page - 1);
                $con.append(boxes);
                This.selectable.add($con.children('.pdfviewer-text').children().get());
            });
        });
    }
    search(e) {
        if (e.which !== 13) {
            return false;
        }
        let This = e.data.object,
            $t = $(this),
            pageCount = $('.pdfviewer-right > div:last-child').data('page'),
            $noresultsCont = $('#pdfviewer-results > .pdfviewer-no-results-container'),
            $resultsCont = $('#pdfviewer-results > .pdfviewer-results-container'),
            $progressBar = $('#pdfviewer-search-progress'),
            $pagesCont = $('#pdfviewer-pages'),
            showProgress;
        // Reset search results.
        $noresultsCont.removeClass('d-none');
        $resultsCont.empty();
        $pagesCont.find('.pdfviewer-result-boxes').remove();
        if (typeof This.sseSearch === 'object' && This.sseSearch.readyState !== 2) {
            This.sseSearch.close();
            clearTimeout(showProgress);
            $progressBar.addClass('d-none');
            $progressBar.find('div').width('0%').attr('aria-value-now', '0%');
        }
        showProgress = setTimeout(function() {
            $progressBar.removeClass('d-none');
        }, 500);
        This.sseSearch = new EventSource(
            IL_BASE_URL + 'index.php/pdf/search?id=' + $('body').data('id') + '&query=' + $t.val()
        );
        This.sseSearch.onmessage = function(e) {
            if (e.data === 'CLOSE' || $pagesCont.length === 0) {
                This.sseSearch.close();
                clearTimeout(showProgress);
                $progressBar.addClass('d-none');
                $progressBar.find('div').width('0%').attr('aria-value-now', '0%');
            } else {
                let response = JSON.parse(e.data);
                _.forEach(response.boxes, function (boxes, page) {
                    let $con = $pagesCont.find('.pdfviewer-page').eq(page - 1).children('img');
                    $con.after(boxes);
                });
                _.forEach(response.snippets, function (snippet) {
                    $(snippet).appendTo('#pdfviewer-results > .pdfviewer-results-container');
                    if ($noresultsCont.hasClass('d-none') === false) {
                        $noresultsCont.addClass('d-none');
                    }
                });
                let progress = Math.floor(Math.min(100, 100 * response.last_page / pageCount));
                $progressBar.find('div').width(progress + '%').attr('aria-value-now', progress + '%');
                // Click on the first result only once.
                if ($resultsCont.find('a.bg-dark').length === 0) {
                    $resultsCont.find('a').eq(0).trigger('click').focus();
                }
            }
        }
        // Make results visible in left panel.
        This.showResults();
        // Open left panel on horizontal layouts.
        if ($('.navbar-toggler').is(':visible') === false) {
            This.showLeft();
        }
    }
    showResults(e) {
        let This = typeof e === 'object' ? e.data.object : this;
        $('#pdfviewer-thumbs').addClass('d-none');
        $('#pdfviewer-bookmarks').addClass('d-none');
        $('#pdfviewer-notes').addClass('d-none');
        $('#pdfviewer-results').removeClass('d-none');
        This.redrawNoteLine();
        This.redrawSnippetLine();
    }
    scrollToResult(e) {
        let This = e.data.object;
        $('#pdfviewer-results .snippet').removeClass('bg-dark');
        $(this).addClass('bg-dark');
        let $el = $('#' + $(this).data('box'));
        This.drawSnippetLine($(this).data('box'));
        This.scrollToElement($el);
        $('.pdfviewer-result-boxes > div').removeClass('active');
        $el.addClass('active');
    }
    nextResult() {
        let $t = $('#pdfviewer-results a.bg-dark').next();
        if ($t.length === 1) {
            $t.trigger('click');
        } else {
            $('#pdfviewer-results a').eq(0).trigger('click');
        }
    }
    prevResult() {
        let $t = $('#pdfviewer-results a.bg-dark').prev();
        if ($t.length === 1) {
            $t.trigger('click');
        } else {
            $('#pdfviewer-results a').last().trigger('click');
        }
    }
    showNotes(e) {
        let This = e.data.object;
        $('#pdfviewer-thumbs').addClass('d-none');
        $('#pdfviewer-bookmarks').addClass('d-none');
        $('#pdfviewer-notes').removeClass('d-none');
        $('#pdfviewer-results').addClass('d-none');
        This.redrawSnippetLine();
        $.when(model.load({
            url: window.IL_BASE_URL + 'index.php/pdf/notelist',
            data: {
                id: $('body').data('id')
            }
        })).done(function (response) {
            $('#pdfviewer-notes').html(response.list);
            _.forEach(response.pages, function (boxes, page) {
                let $con = $('#pdfviewer-pages').find('.pdfviewer-page').eq(page - 1);
                $con.find('.pdfviewer-notes').remove();
                $con.append(boxes);
            });
            $('.pdfnote').tooltip({
                animation: false,
                template: '<div class="tooltip" role="tooltip"><div class="tooltip-inner bg-secondary rounded-0 text-left px-3 py-2 border border-light"></div></div>'
            }).on('shown.bs.tooltip', function () {
                if (typeof window.MathJax.typeset === 'function') {
                    window.MathJax.typeset();
                }
                $(this).tooltip('update');
            });
            $('#pdfviewer-notes').on('click', '.note-btn', function () {
                let noteId = $(this).data('id');
                This.scrollToElement($('#pdfnote-' + noteId));
                $('.note-btn').removeClass('active');
                $(this).addClass('active');
                $('.pdfnote').tooltip('hide');
                This.drawNoteLine(noteId);
            });
            $('.pdfnote').on('click', function () {
                let noteId = $(this).data('id');
                $('#pdfviewer-notes .note-btn').removeClass('active');
                $('#pdfviewer-notes').find('.note-btn[data-id="' + noteId + '"]').addClass('active');
                This.drawNoteLine(noteId);
            });
            if (typeof window.MathJax.typeset === 'function') {
                window.MathJax.typeset();
            }
        });
    }
    /**
     * Click on the Edit note button.
     */
    editNote(e) {
        let $par = $(this).parent();
        $par.addClass('d-none');
        $par.next().removeClass('d-none');
    }
    saveNote(e) {
        let This = e.data.object, $f = $(this), note = $f.find('textarea').val(), noteId = $f.parent().data('id') || 0, itemId = $('body').data('id');
        e.preventDefault();
        $.when(model.save({
            url: $f.attr('action'),
            data: $f.serialize() + '&id=' + itemId,
            async: true
        })).done(function () {
            $f.addClass('d-none');
            if ($.trim(note) === '' || noteId === 0) {
                This.showNotes(e);
            } else {
                $f.prev().removeClass('d-none').find('button').eq(0).text(note);
                $('#pdfnote-' + noteId).attr('data-title', note).attr('data-original-title', note);
                if (typeof window.MathJax.typeset === 'function') {
                    window.MathJax.typeset();
                }
            }
        });
    }
    createNewNote(e) {
        let This = e.data.object;
        if ($('.pdfviewer-page').hasClass('cursor-cross') === true) {
            This.clearNewNote();
        } else {
            This.clearHighlights(e);
            This.showNotes(e);
            This.showLeft();
            $('.pdfviewer-page').addClass('cursor-cross');
            $('.pdfviewer-page').on('click.createnote', '.pdfviewer-notes', function (e) {
                let left = Math.round(1000 * (e.pageX - $(this).offset().left) / $(this).width()),
                    top = Math.round(1000 * (e.pageY - $(this).offset().top) / $(this).height()),
                    pg = $(this).closest('.pdfviewer-page').data('page'),
                    $f = $('#new-note-form');
                $f.removeClass('d-none').find('[name="pg"]').val(pg);
                $f.find('[name="left"]').val(left);
                $f.find('[name="top"]').val(top);
                $('.pdfnote.new').remove();
                $('<div class="pdfnote new" style="left:' + left / 10 + '%;top:' + top / 10 + '%;"></div>').appendTo(this);
                This.clearNewNote();
            });
        }
    }
    clearNotes(e) {
        let This = e.data.object;
        This.clearNewNote();
        $('#pdfviewer-notes').empty();
        $('#pdfviewer-pages').find('.pdfviewer-notes').remove();
    }
    clearNewNote() {
        $('.pdfviewer-page').removeClass('cursor-cross');
        $('.pdfviewer-page').off('click.createnote');
    }
    drawNoteLine(noteId) {
        let $note = $('#pdfnote-' + noteId),
            $noteBtn = $('#pdfviewer-notes').find('.note-group[data-id="' + noteId + '"]'),
            noteRect = $note[0].getBoundingClientRect(),
            noteBtnRect = $noteBtn[0].getBoundingClientRect(),
            leftPos = noteBtnRect.right,
            topPos = Math.min((noteBtnRect.top + 15), noteRect.top),
            svgWidth = noteRect.left - noteBtnRect.right,
            svgHeight = noteRect.top - (noteBtnRect.top + 15);
        let hiddenClass = '';
        // Hide if note out of viewport.
        if ($noteBtn[0].offsetParent === null || topPos < 50 || (svgHeight + topPos) > $(window).height() || svgWidth < 1) {
            hiddenClass = 'd-none';
        }
        // Draw bottom-to-top/flipped line.
        let flipLine = '';
        if (svgHeight < 0) {
            flipLine = 'transform="scale (-1, 1)" transform-origin="center"';
            svgHeight = Math.abs(svgHeight);
        }
        // If exactly horizontal, add 1px height.
        if (svgHeight === 0) {
            svgHeight = 1;
        }
        let line = `
            <svg
                id="note-line" data-note-id="${noteId}" class="${hiddenClass}"
                style="pointer-events: none;position: fixed;width: ${svgWidth}px;height: ${svgHeight}px;top: ${topPos}px;left: ${leftPos}px"
                viewBox="0 0 ${svgWidth} ${svgHeight}"
                xmlns="http://www.w3.org/2000/svg">
                <line x1="0" y1="0" x2="100%" y2="100%"
                    stroke="rgba(47, 141, 237, 0.75)" stroke-dasharray="0, 6" stroke-width="2" stroke-linecap="round"
                    ${flipLine} />
            </svg>`;
        // Prepend the line to the highlight container.
        $('#note-line').remove();
        $note.parent().prepend(line);
    }
    redrawNoteLine() {
        let $line = $('#note-line');
        if ($line.length === 1) {
            this.drawNoteLine($line.attr('data-note-id'));
        }
    }
    drawSnippetLine(snippetId) {
        let $snippet = $('#' + snippetId),
            $snippetBtn = $('.pdfviewer-results-container').find('[data-box="' + snippetId + '"]'),
            snippetRect = $snippet[0].getBoundingClientRect(),
            snippetBtnRect = $snippetBtn[0].getBoundingClientRect(),
            leftPos = snippetBtnRect.right,
            topPos = Math.min((snippetBtnRect.top + 20), snippetRect.top),
            svgWidth = snippetRect.left - snippetBtnRect.right,
            svgHeight = snippetRect.top - (snippetBtnRect.top + 20);
        let hiddenClass = '';
        // Hide if snippet box out of viewport.
        if ($snippetBtn[0].offsetParent === null || topPos < 50 || (svgHeight + topPos) > $(window).height() || svgWidth < 1) {
            hiddenClass = 'd-none';
        }
        // Draw bottom-to-top/flipped line.
        let flipLine = '';
        if (svgHeight < 0) {
            flipLine = 'transform="scale (-1, 1)" transform-origin="center"';
            svgHeight = Math.abs(svgHeight);
        }
        // If exactly horizontal, add 1px height.
        if (svgHeight === 0) {
            svgHeight = 1;
        }
        let line = `
            <svg
                id="snippet-line" data-snippet-id="${snippetId}" class="${hiddenClass}"
                style="pointer-events: none;position: fixed;width: ${svgWidth}px;height: ${svgHeight}px;top: ${topPos}px;left: ${leftPos}px"
                viewBox="0 0 ${svgWidth} ${svgHeight}"
                xmlns="http://www.w3.org/2000/svg">
                <line x1="0" y1="0" x2="100%" y2="100%"
                    stroke="rgba(255, 0, 255, 0.75)" stroke-dasharray="0, 6" stroke-width="2" stroke-linecap="round"
                    ${flipLine} />
            </svg>`;
        // Prepend the line to the highlight container.
        $('#snippet-line').remove();
        $snippet.parent().prepend(line);
    }
    redrawSnippetLine() {
        let $line = $('#snippet-line');
        if ($line.length === 1) {
            this.drawSnippetLine($line.attr('data-snippet-id'));
        }
    }
    openLink(e) {
        let This = e.data.object, href = $(this).data('href');
        if(typeof href === 'number') {
            This.scrollToPage(href);
        } else {
            window.open(href);
        }
    }
}

views.pdfmain = new PdfMainView();

/**
 * PDF management.
 */
class PdfManageView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
    }
    afterRender(data) {
        // File upload form.
        $('#upload-form').uploadable();
        // Delete button.
        $('#delete-pdf').confirmable({
            submit: function () {
                let itemId = $('body').data('id');
                $.when(model.save({
                    url: window.IL_BASE_URL + 'index.php/pdf/delete',
                    data: {id: itemId
                    }
                })).done(function () {
                    B.history.loadUrl();
                });
            }
        });
        $('#reindex-pdf').confirmable({
            submit: function () {
                let itemId = $('body').data('id');
                $.when(model.save({
                    url: window.IL_BASE_URL + 'index.php/pdf/extract',
                    data: {id: itemId
                    }
                })).done(function () {
                    B.history.loadUrl();
                });
            }
        });
        $('#ocr-pdf').confirmable({
            submit: function () {
                let $f = $('#ocr-form');
                $.when(model.save({
                    url: $f.attr('action'),
                    data: $f.serialize()
                })).done(function () {
                    B.history.loadUrl();
                });
            }
        });
        $('body').off('submit.pdfmanage').on('submit.pdfmanage', '#ocr-form', function (e) {
            e.preventDefault();
        });
        // Title list.
        if (typeof itemview === 'object') {
            itemview.clickTitle();
            itemview.changeTitleLink();
        }
    }
}

views.pdfmanage = new PdfManageView();

/*
 * Import.
 */
class ImportWizardView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
    }
}

views.importwizard = new ImportWizardView();

class ImportUidView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.inputType = '';
        this.events = {
            'click #fetch-record': 'fetch',
            'input #uid': 'uidResolve',
            'keydown #uid': 'preventSubmit'
        };
    }
    afterRender(data) {
        formStyle.init();
        $('#upload-form').uploadable();
        $('#tag-filter').filterable({
            complete: function () {
                $('#content-col .label-text').each(function () {
                    if ($(this).hasClass('d-none')) {
                        $(this).parent().parent().addClass('d-none');
                    } else {
                        $(this).parent().parent().removeClass('d-none');
                    }
                });
                $('.tag-table').find('tr').each(function() {
                    if($(this).find('.label-text').length - $(this).find('.label-text.d-none').length === 0) {
                        $(this).addClass('d-none');
                    } else {
                        $(this).removeClass('d-none');
                    }
                });
            }
        });
    }
    uidResolve(e) {
        let uidVal = $.trim($(this).val());
        if (/^10\.\d{4}/.test(uidVal) === true) {
            $('#new-uid-type').val('DOI');
        } else if (/^http/i.test(uidVal) === true) {
            let url = new URL(uidVal), path = url.pathname.substr(1);
            if (/^10\.\d{4}/.test(path) === true) {
                $(this).val(path);
                $('#new-uid-type').val('DOI');
            }
        } else if (/^pmid:/i.test(uidVal) === true) {
            $('#new-uid-type').val('PMID');
        } else if (/^ieee:/i.test(uidVal) === true) {
            $('#new-uid-type').val('IEEE');
        } else if (/^arxiv:/i.test(uidVal) === true) {
            $('#new-uid-type').val('ARXIV');
        } else if (/^[0-9]{4}.{14}[A-Z]/.test(uidVal) === true) {
            $('#new-uid-type').val('NASAADS');
        } else if (/^pmc.*|pmcid:/i.test(uidVal) === true) {
            $('#new-uid-type').val('PMCID');
        } else if (/^OL/.test(uidVal) === true) {
            $('#new-uid-type').val('OL');
        } else if (/^[A-Z]{2}\d+/.test(uidVal) === true) {
            $('#new-uid-type').val('PAT');
        } else if (/^[SP]\d/.test(uidVal) === true) {
            $('#new-uid-type').val('DIRECT');
        } else if (/^ISBN:\s?\d/.test(uidVal) === true) {
            $('#new-uid-type').val('ISBN');
        } else {
            $('#new-uid-type').val('');
        }
        $('#select-type').collapse('show');
    }
    fetch(e) {
        let uid = $.trim($('#uid').val());
        switch ($('#new-uid-type').val()) {
            case 'DOI':
                $.when(model.load({
                    url: window.IL_BASE_URL + 'index.php/crossref/fetch',
                    data: {uid: uid}
                })).done(function (response) {
                    let item = response.items[0] || [];
                    if (typeof item.title === 'undefined') {
                        $('#uid-message').removeClass('d-none').html('No record found. Try another UID.');
                    } else {
                        $('.import-wizard-uid').html('Record found:<h5><a href="' + item.urls[0] + '" target="_blank">'
                            + item.title + '</a></h5>');
                        $('.uploadable-url').val(item.urls[1] || '');
                        $('#metadata').val(JSON.stringify(item));
                        $('#fetch-record').addClass('d-none');
                        $('#upload-form').find(':submit').removeClass('d-none');
                        $('#phase-2').removeClass('d-none');
                    }
                }).fail(function (xhr) {
                    if (xhr.status === 404) {
                        $('#uid-message').removeClass('d-none').html('No record found. Try another UID.');
                    }
                });
                break;
            case 'PMID':
                let pmid = uid.replace(/\D/g, '');
                $.when(model.load({
                    url: 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&retmode=xml&id=' + pmid,
                    cors: true,
                    dataType: 'text'
                })).done(function (response) {
                    $.when(model.save({
                        url: window.IL_BASE_URL + 'index.php/pubmed/format',
                        data: {xml: response}
                    })).done(function (response) {
                        let item = response.items[0] || [];
                        if (typeof item.title === 'undefined') {
                            $('#uid-message').removeClass('d-none').html('No record found. Try another UID.');
                        } else {
                            $('.import-wizard-uid').html('Record found:<h5><a href="' + item.urls[0] + '" target="_blank">'
                                + item.title + '</a></h5>');
                            $('.uploadable-url').val(item.urls[1] || '');
                            $('#metadata').val(JSON.stringify(item));
                            $('#fetch-record').addClass('d-none');
                            $('#upload-form').find(':submit').removeClass('d-none');
                            $('#phase-2').removeClass('d-none');
                        }
                    });
                });
                break;
            case 'PMCID':
                let pmcid = $.trim(uid.replace(/pmcid:/gi, ''));
                $.when(model.load({
                    url: 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pmc&retmode=xml&id=' + pmcid,
                    cors: true,
                    dataType: 'text'
                })).done(function (response) {
                    $.when(model.save({
                        url: window.IL_BASE_URL + 'index.php/pmc/format',
                        data: {xml: response}
                    })).done(function (response) {
                        let item = response.items[0] || [];
                        if (typeof item.title === 'undefined') {
                            $('#uid-message').removeClass('d-none').html('No record found. Try another UID.');
                        } else {
                            $('.import-wizard-uid').html('Record found:<h5><a href="' + item.urls[0] + '" target="_blank">'
                                + item.title + '</a></h5>');
                            $('.uploadable-url').val(item.urls[1] || '');
                            $('#metadata').val(JSON.stringify(item));
                            $('#fetch-record').addClass('d-none');
                            $('#upload-form').find(':submit').removeClass('d-none');
                            $('#phase-2').removeClass('d-none');
                        }
                    });
                });
                break;
            case 'ISBN':
                let isbn = $.trim(uid.replace(/isbn:/gi, ''));
                $.when(model.load({
                    url: 'https://openlibrary.org/api/books?bibkeys=ISBN:' + isbn + '&jscmd=data&format=json',
                    cors: true,
                    dataType: 'text'
                })).done(function (response) {
                    $.when(model.save({
                        url: window.IL_BASE_URL + 'index.php/ol/convert',
                        data: {json: response}
                    })).done(function (response) {
                        let item = response.items[0] || [];
                        if (typeof item.title === 'undefined') {
                            $('#uid-message').removeClass('d-none').html('No record found. Try another UID.');
                        } else {
                            $('.import-wizard-uid').html('Record found:<h5><a href="' + item.urls[0] + '" target="_blank">'
                                + item.title + '</a></h5>');
                            $('#metadata').val(JSON.stringify(item));
                            $('#fetch-record').addClass('d-none');
                            $('#upload-form').find(':submit').removeClass('d-none');
                            $('#phase-2').removeClass('d-none');
                        }
                    });
                });
                break;
            case 'OL':
                let olid = $.trim(uid.replace(/isbn:/gi, ''));
                $.when(model.load({
                    url: 'https://openlibrary.org/api/books?bibkeys=OLID:' + olid + '&jscmd=data&format=json',
                    cors: true,
                    dataType: 'text'
                })).done(function (response) {
                    $.when(model.save({
                        url: window.IL_BASE_URL + 'index.php/ol/convert',
                        data: {json: response}
                    })).done(function (response) {
                        let item = response.items[0] || [];
                        if (typeof item.title === 'undefined') {
                            $('#uid-message').removeClass('d-none').html('No record found. Try another UID.');
                        } else {
                            $('.import-wizard-uid').html('Record found:<h5><a href="' + item.urls[0] + '" target="_blank">'
                                + item.title + '</a></h5>');
                            $('#metadata').val(JSON.stringify(item));
                            $('#fetch-record').addClass('d-none');
                            $('#upload-form').find(':submit').removeClass('d-none');
                            $('#phase-2').removeClass('d-none');
                        }
                    });
                });
                break;
            case 'IEEE':
                let xploreid = uid.replace(/ieee:\s?/i, '');
                $.when(model.load({
                    url: window.IL_BASE_URL + 'index.php/ieee/fetch',
                    data: {uid: xploreid}
                })).done(function (response) {
                    let item = response.items[0];
                    $('.import-wizard-uid').html('Record found:<h5><a href="https://ieeexplore.ieee.org/document/"' + xploreid
                        + ' target="_blank">' + item.title + '</a></h5>');
                    $('#metadata').val(JSON.stringify(item));
                    $('#fetch-record').addClass('d-none');
                    $('#upload-form').find(':submit').removeClass('d-none');
                    $('#phase-2').removeClass('d-none');
                });
                break;
            case 'ARXIV':
                let arxivid = uid.replace(/arxiv:\s?/i, '');
                $.when(model.load({url: arxiv.url + arxivid, cors: true, dataType: 'xml'})).done(function (response) {
                    let entry = $(response).find('entry'), itemTitle = entry.children('title').text();
                    $('.import-wizard-uid').html('Record found:<h5><a href="' + entry.children('id').text() + '" target="_blank">'
                        + itemTitle + '</a></h5>');
                    $('#metadata').val(arxiv.metadata($(response)));
                    $('#fetch-record').addClass('d-none');
                    $('#upload-form').find(':submit').removeClass('d-none');
                    $('#phase-2').removeClass('d-none');
                }).fail(function (xhr) {
                    if (xhr.status === 400) {
                        $('#uid-message').removeClass('d-none').html('No record found. Try another UID.');
                    }
                });
                break;
            case 'NASAADS':
                $.when(model.load({
                    url: window.IL_BASE_URL + 'index.php/nasa/fetch',
                    data: {'uid': uid}
                })).done(function (response) {
                    let item = response.items[0] || [];
                    if (typeof item.title === 'undefined') {
                        $('#uid-message').removeClass('d-none').html('No record found. Try another UID.');
                    } else {
                        $('.import-wizard-uid').html('Record found:<h5><a href="' + item.urls[0] + '" target="_blank">'
                            + item.title + '</a></h5>');
                        $('.uploadable-url').val(item.urls[1] || '');
                        $('#metadata').val(JSON.stringify(item));
                        $('#fetch-record').addClass('d-none');
                        $('#upload-form').find(':submit').removeClass('d-none');
                        $('#phase-2').removeClass('d-none');
                    }
                });
                break;
            case 'PAT':
                let patentId = uid.replace(/patent:\s?/i, '');
                $.when(model.load({
                    url: window.IL_BASE_URL + 'index.php/patents/fetch',
                    data: {'uid': patentId}
                })).done(function (response) {
                    let item = response.items[0] || [];
                    if (typeof item.title === 'undefined') {
                        $('#uid-message').removeClass('d-none').html('No record found. Try another UID.');
                    } else {
                        $('.import-wizard-uid').html('Record found:<h5><a href="' + item.urls[0] + '" target="_blank">'
                            + item.title + '</a></h5>');
                        $('.uploadable-url').val(item.urls[1] || '');
                        $('#metadata').val(JSON.stringify(item));
                        $('#fetch-record').addClass('d-none');
                        $('#upload-form').find(':submit').removeClass('d-none');
                        $('#phase-2').removeClass('d-none');
                    }
                });
                break;
        }
    }
    preventSubmit(e) {
        if (e.which === 13) {
            e.preventDefault();
            e.data.object.fetch();
        }
    }
}

views.importuid = new ImportUidView();

class ImportFileView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
    }
    afterRender(data) {
        formStyle.init();
        $('#upload-form').uploadable();
        $('#tag-filter').filterable({
            complete: function () {
                $('#content-col .label-text').each(function () {
                    if ($(this).hasClass('d-none')) {
                        $(this).parent().parent().addClass('d-none');
                    } else {
                        $(this).parent().parent().removeClass('d-none');
                    }
                });
                $('.tag-table').find('tr').each(function() {
                    if($(this).find('.label-text').length - $(this).find('.label-text.d-none').length === 0) {
                        $(this).addClass('d-none');
                    } else {
                        $(this).removeClass('d-none');
                    }
                });
            }
        });
    }
}

views.importfile = new ImportFileView();

class ImportTextView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
    }
    afterRender(data) {
        formStyle.init();
        $('#upload-form').uploadable();
        $('#tag-filter').filterable({
            complete: function () {
                $('#content-col .label-text').each(function () {
                    if ($(this).hasClass('d-none')) {
                        $(this).parent().parent().addClass('d-none');
                    } else {
                        $(this).parent().parent().removeClass('d-none');
                    }
                });
                $('.tag-table').find('tr').each(function() {
                    if($(this).find('.label-text').length - $(this).find('.label-text.d-none').length === 0) {
                        $(this).addClass('d-none');
                    } else {
                        $(this).removeClass('d-none');
                    }
                });
            }
        });
    }
}

views.importtext = new ImportTextView();

class ImportManualView extends View {
    constructor(EditMainView) {
        super();
        this.parent = '#content-col';
        this.editmain = EditMainView;
    }
    afterRender(data) {
        formStyle.init();
        $('#upload-form').uploadable({
            pdftitles: function (e, data) {
                $('#extracted-titles').empty();
                if (data.titles.length > 0) {
                    // Last found PDF title.
                    $('#title').val(data.titles.pop());
                }
            },
            change: function (e, file) {
                $('#title').val(file.name.substr(0, file.name.lastIndexOf('.')));
            },
            clear: function (e) {
                $('#title').val('');
            }
        });
        this.editmain.uploadFormWidgets();
        $('#tag-filter').filterable({
            complete: function () {
                $('#content-col .label-text').each(function () {
                    if ($(this).hasClass('d-none')) {
                        $(this).parent().parent().addClass('d-none');
                    } else {
                        $(this).parent().parent().removeClass('d-none');
                    }
                });
                $('.tag-table').find('tr').each(function() {
                    if($(this).find('.label-text').length - $(this).find('.label-text.d-none').length === 0) {
                        $(this).addClass('d-none');
                    } else {
                        $(this).removeClass('d-none');
                    }
                });
            }
        });
    }
}

views.importmanual = new ImportManualView(views.editmain);

class ExternalMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit .search-form': 'search',
            'click .delete-search': 'deleteSearch',
            'click .edit-search': 'loadForm'
        };
    }
    afterRender(data) {
        formStyle.init();
        $('.clone-button').clonable({target: '#search-row'});
        // Saveable should appear after clonable, so it can clone/load extra rows.
        $(this.parent).find('form').saveable();
    }
    search(e) {
        e.preventDefault();
        $(this).saveable('save');
        let params = $(this).serialize();
        router.navigate($(this).attr('action') + '?' + params, {trigger: true});
    }
    deleteSearch(e) {
        let $t = $(this);
        $.when(model.save({
            url: $t.data('url'),
            data: {
                id: $t.data('id')
            }
        })).done(function () {
            B.history.loadUrl();
        });
    }
    loadForm() {
        let href = $(this).closest('.list-group-item').find('a').attr('href'), url = new URL('http://foo.bar/' + href.substr(1));
        let entries = url.searchParams.entries(), result = [];
        for(let entry of entries) {
            const [key, val] = entry;
            if (val !== '') {
                result.push({
                    name: key,
                    value: val
                });
            }
        }
        let $f = $('#content-col').find('form');
        $f.saveable('saveParams', result);
        $f.saveable('load');
    }
}

views.ieeemain = new ExternalMainView();
views.arxivmain = new ExternalMainView();
views.crossrefmain = new ExternalMainView();
views.pubmedmain = new ExternalMainView();
views.pmcmain = new ExternalMainView();
views.nasamain = new ExternalMainView();
views.patentsmain = new ExternalMainView();
views.sciencedirectmain = new ExternalMainView();
views.scopusmain = new ExternalMainView();

class ExternalSearchView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'click .add-pdf-btn': 'makeUploadable',
            'click button': 'makeUploadable'
        };
    }
    afterRender(data) {
        formStyle.init();
        $(window).off('resize.ExternalSearchView').on('resize.ExternalSearchView', function () {
            views.itemsmain.itemsHeight();
        });
        views.itemsmain.itemsHeight();
        $('#content-col .tag-filter').filterable({
            complete: function () {
                let $par = $(this).closest('.collapse'), $tab = $(this).closest('.collapse').find('table');
                $par.find('.label-text').each(function () {
                    if ($(this).hasClass('d-none')) {
                        $(this).parent().parent().addClass('d-none');
                    } else {
                        $(this).parent().parent().removeClass('d-none');
                    }
                });
                $tab.find('tr').each(function() {
                    if($(this).find('.label-text').length - $(this).find('.label-text.d-none').length === 0) {
                        $(this).addClass('d-none');
                    } else {
                        $(this).removeClass('d-none');
                    }
                });
            }
        });
    }
    makeUploadable() {
        $(this).closest('form').uploadable();
    }
}

views.ieeesearch = new ExternalSearchView();
views.arxivsearch = new ExternalSearchView();
views.crossrefsearch = new ExternalSearchView();
views.pubmedsearch = new ExternalSearchView();
views.pmcsearch = new ExternalSearchView();
views.nasasearch = new ExternalSearchView();

class GlobalSettingsMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit #settings-form': 'submitForm'
        };
    }
    afterRender(data) {
        // Form beautification.
        formStyle.init();
    }
    submitForm(e) {
        e.preventDefault();
        let $f = $(this), pacUrl = $.trim($('#wpad-url').val());
        if ($('#connection-url').prop('checked') === true && pacUrl !== '') {
            $.getScript(pacUrl, function () {
                let proxystr = FindProxyForURL('', 'www.crossref.org'), proxyurl = '';
                let parts = proxystr.split(';');
                _.forEach(parts, function (part) {
                    let trimmedPart = _.trim(part);
                    if (trimmedPart.indexOf('PROXY') === 0) {
                        proxyurl = part.substr(6);
                        $('#proxy-pac').val(proxyurl);
                        return false;
                    }
                });
                $.when(model.save({
                    url: $f.attr('action'),
                    data: $f.serialize()
                }));
            }).fail(function () {
                $.jGrowl('Unable to fetch the PAC/WPAD file.', {header: 'Error', theme: 'bg-danger'});
            });
        } else {
            $.when(model.save({
                url: $f.attr('action'),
                data: $f.serialize()
            }));
        }
    }
}

views.globalsettingsmain = new GlobalSettingsMainView();

class UsersMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'click .update-user': 'updateUser',
            'click #create-user': 'createUser'
        };
    }
    afterRender(data) {
        let This = this;
        $('.reset-password').confirmable({
            submit: This.resetPassword
        });
    }
    updateUser() {
        let $par = $(this).closest('tr'), email = $par.find('.email').val();
        if ($.trim(email) === '') {
            $.jGrowl('Email is required.', {header: 'Info', theme: 'bg-primary'});
            return;
        }
        $.when(model.save({
            url: window.IL_BASE_URL + 'index.php/users/update',
            data: {
                user: {
                    'username':    $par.find('.username').text(),
                    'first_name':  $par.find('.first-name').val(),
                    'last_name':   $par.find('.last-name').val(),
                    'email':       email,
                    'permissions': $par.find('.permissions').val(),
                    'status':      $par.find('.status').val()
                }
            }
        })).done(function () {
            B.history.loadUrl();
        });
    }
    createUser() {
        let $par = $(this).closest('tr'), email = $par.find('.email').val();
        if ($.trim(email) === '') {
            $.jGrowl('Email is required.', {header: 'Info', theme: 'bg-primary'});
            return;
        }
        $.when(model.save({
            url: window.IL_BASE_URL + 'index.php/users/create',
            data: {
                user: {
                    'username':    $par.find('.username').val(),
                    'first_name':  $par.find('.first-name').val(),
                    'last_name':   $par.find('.last-name').val(),
                    'email':       email,
                    'permissions': $par.find('.permissions').val()
                }
            }
        })).done(function (response) {
            if (typeof response.password === 'string') {
                $.jGrowl(response.password, {
                    sticky: true,
                    theme: 'bg-dark text-light'
                });
            }
            B.history.loadUrl();
        });
    }
    resetPassword() {
        let $par = $(this).closest('tr'), email = $par.find('.email').val();
        if ($.trim(email) === '') {
            $.jGrowl('User\'s email is required to reset password.', {header: 'Info', theme: 'bg-primary'});
            return;
        }
        $.when(model.save({
            url: window.IL_BASE_URL + 'index.php/users/reset',
            data: {
                user: {
                    'username': $par.find('.username').text(),
                    'email':    email
                }
            }
        })).done(function (response) {
            if (typeof response.password === 'string') {
                $.jGrowl(response.password, {
                    sticky: true,
                    theme: 'bg-dark'
                });
            }
        });
    }
}

views.usersmain = new UsersMainView();

class DetailsMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
    }
}

views.detailsmain = new DetailsMainView();

class LogsMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit .form-chart': 'submitForm',
            'submit #form-list': 'drawTable'
        };
    }
    afterRender(data) {
        formStyle.init();
        chart.draw('chart-opens', _.keys(data.opens), null, _.values(data.opens));
        chart.draw('chart-pages', _.keys(data.pages), null, _.values(data.pages));
        chart.draw('chart-downloads', _.keys(data.downloads), null, _.values(data.downloads));
        window.tables['table-logs'] = $('#table-logs').DataTable({
            "deferRender": true,
            "order": [[2, 'desc'],[0, 'asc']]
        });
    }
    submitForm(e) {
        e.preventDefault();
        let $f = $(this);
        $.when(model.load({
            url: $f.attr('action'),
            data: $f.serialize()
        })).done(function (data) {
            if (typeof data.opens !== 'undefined') {
                chart.draw('chart-opens', _.keys(data.opens), null, _.values(data.opens));
            } else if (typeof data.pages !== 'undefined') {
                chart.draw('chart-pages', _.keys(data.pages), null, _.values(data.pages));
            } else if (typeof data.downloads !== 'undefined') {
                chart.draw('chart-downloads', _.keys(data.downloads), null, _.values(data.downloads));
            }
        });
    }
    drawTable(e) {
        e.preventDefault();
        let $f = $(this);
        $.when(model.load({
            url: $f.attr('action'),
            data: $f.serialize()
        })).done(function (data) {
            window.tables['table-logs'].clear().rows.add(data).draw();
        });
    }
}

views.logsmain = new LogsMainView();

class CitationMainView extends View {
    constructor() {
        super();
        this.parent = '#content-col';
        this.events = {
            'submit #form-new-csl': 'submitForm'
        };
    }
    afterRender(data) {
        window.tables['table-csl'] = $('#table-csl').DataTable({
            "deferRender": true,
            data: data.styles,
            columnDefs: [
                {orderable: false, targets: 2}
            ]
        });
        $('#modal-csl').off('shown.bs.modal').on('shown.bs.modal', function (e) {
            $('#modal-csl').find('.modal-title').text($(e.relatedTarget).attr('data-name'));
            $.when(model.load({
                url: window.IL_BASE_URL + 'index.php/citation/get',
                data: {
                    id: $(e.relatedTarget).attr('data-id')
                }
            })).done(function (response) {
                $('#modal-csl').find('code').html(response.csl);
            });
        });
    }
    submitForm(e) {
        e.preventDefault();
        let $f = $(this);
        $.when(model.save({
            url: $f.attr('action'),
            data: $f.serialize()
        })).done(function () {
            $f[0].reset();
        });
    }
}

views.citationmain = new CitationMainView();

/*
 * HTML views.
 */

/**
 * Main.
 */
class MainView {
    constructor() {
        let This = this;
        // Sidebar repainting.
        sidebar.init();
        // When initially loading the signed-in view, show the dashboard.
        if ((location.hash ===  '' || location.hash ===  '#') && $('#side-menu').length === 1) {
            router.navigate('dashboard/main', {trigger: true, replace: true});
        }
        // Sign in form.
        $('#signin-form').on('submit', function (e) {
            e.preventDefault();
            let $f=$(this),
                parsedUrl = new URL(window.location.href),
                enRef = parsedUrl.searchParams.get("ref");
            if ($.trim($f.find('[name="username"]').val()) === '' || $.trim($f.find('[name="password"]').val()) === '') {
                return;
            }
            $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function (response) {
                if (typeof response.info === 'undefined') {
                    if (typeof enRef === 'string') {
                        let ref = window.atob(enRef),
                            refUrl = new URL(ref);
                        // Only reload if hosts match.
                        if (refUrl.hostname === location.hostname) {
                            location.replace(ref);
                        } else {
                            location.replace(IL_BASE_URL + 'index.php/#dashboard/main');
                        }
                    } else {
                        location.replace(IL_BASE_URL + 'index.php/#dashboard/main');
                    }
                }
            });
        });
        // Migrate form.
        $('#migrate-form').on('submit', function (e) {
            e.preventDefault();
            let $f=$(this);
            $.when(model.load({url: $f.attr('action') + '?' + $f.serialize()})).done(function (response) {
                if (typeof response.info === 'undefined') {
                    location.replace(window.IL_BASE_URL);
                }
            });
        });
        // Sign out.
        $('#sign-out').on('click', function () {
            $.when(model.save({url: window.IL_BASE_URL + 'index.php/authentication/signout'})).done(function () {
                location.assign(window.IL_BASE_URL);
            });
        });
        // Modals.
        $('.modal-content').draggable({
            containment: 'document',
            handle: '.modal-header'
        });
        // Extended keyboard.
        $('#keyboard-toggle').on('click', function () {
           keyboard.init();
        });
        // Display settings.
        $('#content-col').on('click', '#open-settings', function () {
            if ($.trim($('#modal-settings').find('.modal-body').html()) === '') {
                $.when(model.load({
                    url: window.IL_BASE_URL + 'index.php/settings/display'
                })).done(function (response) {
                    $('#modal-settings').find('.modal-body').html(response.html);
                    formStyle.init();
                });
            }
        });
        // Bind submit event to modal button.
        $('#modal-settings .modal-footer button').eq(0).on('click', function (e) {
            $('#modal-settings form').trigger('submit');
        });
        $('#modal-settings').on('submit', 'form', function (e) {
            e.preventDefault();
            let $f = $(this);
            $.when(model.save({
                url: $f.attr('action'),
                data: $f.serialize()
            })).done(function () {
                B.history.loadUrl();
            });
            $('#modal-settings').modal('hide');
        });
        searchlist.init();
    }
}

/**
 * Migration.
 */
class MigrationView {
    constructor() {
        // Migrate form.
        $('#migrate-form').on('submit', function (e) {
            e.preventDefault();
            let $f=$(this);
            $.when(model.load({url: $f.attr('action') + '?' + $f.serialize()})).done(function (response) {
                if (typeof response.info === 'undefined') {
                    location.replace(window.IL_BASE_URL);
                }
            });
        });
    }
}

/**
 * Registration
 */
class RegistrationView {
    constructor() {
        // Registration form.
        $('#signup-form').on('submit', function (e) {
            e.preventDefault();
            let $f=$(this);
            $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function (response) {
                if (typeof response.info === 'undefined') {
                    location.assign(window.IL_BASE_URL);
                }
            });
        });
    }
}

/**
 * Reset password.
 */
class ResetpasswordView {
    constructor() {
        // Form.
        $('form').on('submit', function (e) {
            e.preventDefault();
            let $f=$(this);
            $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function (response) {
                $('.container-fluid').html(response.html);
            });
        });
        $('body').on('click', '#new-password', function () {
            let sel = window.getSelection(), range = document.createRange();
            range.selectNodeContents($('#new-password')[0]);
            sel.removeAllRanges();
            sel.addRange(range);
        });
    }
}

/**
 * Item.
 */
class ItemView {
    constructor() {
        // Sidebar repainting.
        let This = this;
        sidebar.init();
        // Add back url to the top link.
        let backURL = typeof store.load('il.itemReferrer') === 'string' ?
            store.load('il.itemReferrer') :
            window.IL_BASE_URL + '#items/main';
        $('#item-back-link').attr('href', backURL);
        let params = (new URL('http://foo.bar/' + location.hash.substring(1))).searchParams,
            anId = params.get('id') || '';
        // Add id to links and BODY.
        $('body').attr('data-id', anId).data('id', anId);
        // Notes form.
        $('#id-hidden').val(anId);
        $('a.add-id-link').each(function () {
            let thisHParts = $(this).attr('href').split('id=');
            $(this).attr('href', thisHParts[0] + 'id=' + anId);
        });
        // Extended keyboard.
        $('#keyboard-toggle').on('click', function () {
            keyboard.init();
        });
        // Sign out.
        $('#sign-out').on('click', function () {
            $.when(model.save({url: window.IL_BASE_URL + 'index.php/authentication/signout'})).done(function () {
                location.assign(window.IL_BASE_URL);
            });
        });
        // Init title list.
        let itemList = sessionStore.load('il.idList');
        if (itemList === null || itemList.length < 2) {
            $('#left-item-list').addClass('d-none');
        } else {
            // Fetch the template link.
            let $l = $('#left-item-list .left-item-link').detach(), href = $l.attr('href');
            itemList.forEach(function (v) {
                let isActive = v.id === anId ? 'bg-darker-10' : '';
                $l.clone().addClass(isActive).attr('href', href.replace('{ID}', v.id)).attr('data-id', v.id).html(v.title).appendTo('#left-item-list');
            });
            $l = undefined;
            $('#left-item-list').removeClass('d-none').hide().fadeIn();
        }
    }
    /**
     * Title menu click. Change active class in title list. Change left menu link ids.
     */
    clickTitle() {
        let $par = $('#left-item-list'),
            $activeLink = $('#left-item-list .left-item-link.bg-darker-10');
        if ($activeLink.length === 0) {
            $par.addClass('d-none')
            sessionStore.delete('il.idList');
            return;
        }
        if ($par.hasClass('d-none')) {
            return;
        }
        let params = (new URL('http://foo.bar/' + location.hash.substring(1))).searchParams,
            anId = params.get('id') || '';
        // Add active class.
        $('#left-item-list .left-item-link').each(function () {
            $(this).removeClass('bg-darker-10');
            if ($(this).attr('data-id') === anId) {
                $(this).addClass('bg-darker-10');
            }
        });
        // Scroll to active title.
        let activePos = $activeLink.position().top,
            parTop = $par.scrollTop(),
            activeBox = $activeLink[0].getBoundingClientRect(),
            parBox = $par[0].getBoundingClientRect();
        if (activeBox.top < 10 || (parBox.bottom - activeBox.bottom) < 10) {
            $par.animate({
                'scrollTop': parTop + activePos - ($(window).height() / 3)
            }, 250);
        }
        // Change url id parameters.
        $('a.add-id-link').each(function () {
            let thisHParts = $(this).attr('href').split('id=');
            $(this).attr('href', thisHParts[0] + 'id=' + anId);
        });
        $('body').attr('data-id', anId).data('id', anId);
        // Open notes. Item id changed. Load notes, reload editor if notes initialized.
        if ($('#id-hidden').length === 1 && $('#id-hidden').val() !== anId) {
            $.when(model.load({url: window.IL_BASE_URL + 'index.php/notes/user', data: {id: anId}})).done(function (response) {
                $('#notes-ta').val(response.user.note);
                $('#id-hidden').val(anId);
                if (window.tinymce.activeEditor !== null && window.tinymce.activeEditor.initialized === true) {
                    window.tinymce.activeEditor.load();
                }
            });
        }
    }
    /**
     * Main menu click. Change title link paths based on a current address bar link.
     */
    changeTitleLink() {
        if ($('#left-item-list').hasClass('d-none')) {
            return;
        }
        let href = '#' + (new URL('http://foo.bar/' + location.hash.substring(1))).pathname.substring(1);
        $('#left-item-list .left-item-link').each(function () {
            $(this).attr('href', href + '?id=' + $(this).attr('data-id'));
        });
    }
}

/**
 * Project view.
 */

class ProjectView {
    constructor() {
        let This = this;
        // Sidebar repainting.
        sidebar.init();
        // Add id to links and BODY.
        let params = (new URL('http://foo.bar/' + location.hash.substring(1))).searchParams,
            anId = params.get('id') || '';
        $('body').attr('data-id', anId).data('id', anId);
        $('a.add-id-link').each(function () {
            let thisHParts = $(this).attr('href').split('id=');
            $(this).attr('href', thisHParts[0] + 'id=' + anId);
        });
        // Notes form.
        $('#id-hidden').val(anId);
        // Note-opening buttons.
        $('#content-col').on('click', '.open-notes', {object: this}, this.openNotes);
        // Extended keyboard.
        $('#keyboard-toggle').on('click', function () {
            keyboard.init();
        });
        // Display settings.
        $('#content-col').on('click', '#open-settings', function () {
            if ($.trim($('#modal-settings').find('.modal-body').html()) === '') {
                $.when(model.load({
                    url: window.IL_BASE_URL + 'index.php/settings/display'
                })).done(function (response) {
                    $('#modal-settings').find('.modal-body').html(response.html);
                    formStyle.init();
                });
            }
        });
        // Bind submit event to modal button.
        $('#modal-settings .modal-footer button').eq(0).on('click', function (e) {
            $('#modal-settings form').trigger('submit');
        });
        $('#modal-settings').on('submit', 'form', function (e) {
            e.preventDefault();
            let $f = $(this);
            $.when(model.save({
                url: $f.attr('action'),
                data: $f.serialize()
            })).done(function () {
                B.history.loadUrl();
            });
            $('#modal-settings').modal('hide');
        });
        // Sign out.
        $('#sign-out').on('click', function () {
            $.when(model.save({url: window.IL_BASE_URL + 'index.php/authentication/signout'})).done(function () {
                location.assign(window.IL_BASE_URL);
            });
        });
        searchlist.init();
    }
    /**
     * Open notes. Init, or reload TinyMCE.
     */
    openNotes(e) {
        if (window.tinymce.activeEditor !== null && window.tinymce.activeEditor.initialized === true) {
            // Previously initialized, just show window.
            $('#notes-window').removeClass('d-none');
        } else {
            // Unintialized, load notes.
            e.data.object.loadNotes();
        }
    }
    /**
     * Load textarea with item notes.
     */
    loadNotes() {
        let This = this, projectId = $('body').data('id');
        $.when(model.load({url: window.IL_BASE_URL + 'index.php/project/usernotes', data: {id: projectId}})).done(function (response) {
            $('#notes-ta').val(response.user.note);
            This.initNotes();
        });
    }
    initNotes() {
        window.tinymce.init({
            theme: 'silver',
            selector: '#notes-ta',
            content_css: window.IL_BASE_URL + "css/style.css",
            resize: 'both',
            min_width: 300,
            min_height: 300,
            menubar: false,
            plugins: 'importcss save lists advlist link image code fullscreen table searchreplace',
            toolbar1: 'save undo redo fullscreen code | formatselect link unlink image table searchreplace',
            toolbar2: 'bold italic underline strikethrough subscript superscript removeformat | forecolor backcolor | outdent indent bullist numlist',
            save_onsavecallback: function (editor) {
                let $f = $('#note-form');
                $.when(model.save({url: $f.attr('action'), data: $f.serialize()})).done(function () {
                    $('#user-note').html(editor.getContent());
                    if (typeof window.MathJax.typeset === 'function') {
                        window.MathJax.typeset();
                    }
                });
            },
            image_description: false,
            relative_urls: false,
            remove_script_host: false,
            image_dimensions: false,
            image_class_list: [
                {title: 'Auto width', value: 'mce-img-fluid'}
            ],
        }).then(function () {
            let $nw = $('#notes-window');
            $nw.removeClass('d-none');
            $nw.position({
                my: 'left bottom',
                at: 'left bottom',
                of: '#content-col'
            });
            $(window).off('resize.notes').on('resize.notes', function () {
                $('#notes-window').position({
                    my: 'left bottom',
                    at: 'left bottom',
                    of: '#content-col'
                });
            });
            $nw.draggable({
                handle: ".card-header",
                containment: "body"
            });
            // Window close.
            $nw.find('.close').off('click.notes').on('click.notes', function () {
                $('#notes-window').addClass('d-none');
            });
        });
    }
}

/*
 * When document is ready.
 */
$(function(){
    /**
     * Backbone history.
     */
    B.history.start();
    /*
     * Router catches hash changes; ignores regular links and mailto.
     */
    $('body').on('click', 'a', function () {
        let href = $(this).attr('href');
        // Full, empty and data links are exempt.
        if (href.indexOf('//') > 0 || href.indexOf('mailto') === 0 || href.indexOf('data') === 0) {
            // Store item link referrer in storage.
            if (/\/item#/.test(href) === true) {
                store.save('il.itemReferrer', location.href);
            }
            return true;
        } else if (href === '#' || href === '') {
            return false;
        } else {
            router.navigate(href, {trigger: true});
        }
    });
    // Abort response when overlay is on.
    $('body').on('click', '#abort-request', function () {
        model.abort();
    });
    // Navigation hotkeys.
    $(document).on('keydown.hotkeys', null, 'a', function () {
        $('.navigation-left').trigger('click');
    });
    $(document).on('keydown.hotkeys', null, 'w', function () {
        $('.navigation-left').trigger('click');
    });
    $(document).on('keydown.hotkeys', null, 'd', function () {
        $('.navigation-right').trigger('click');
    });
    $(document).on('keydown.hotkeys', null, 's', function () {
        $('.navigation-right').trigger('click');
    });
    $(document).on('keydown.hotkeys', null, 'esc', function () {
        if ($('#pdfviewer-menu').length === 1 && typeof views.pdfmain === 'object') {
            views.pdfmain.destroyCropper();
            views.pdfmain.destroyTextLayer();
            views.pdfmain.clearNewNote();
            $('#pdfviewer-highlight-menu .highlight-cancel').click();
        }
        if ($('#pdfviewer-menu').length === 1 && typeof window.pdfmainview === 'object') {
            window.pdfmainview.destroyCropper();
            window.pdfmainview.destroyTextLayer();
            window.pdfmainview.clearNewNote();
            $('#pdfviewer-highlight-menu .highlight-cancel').click();
        }
    });
    $(document).on('keydown.hotkeys', null, 'h', function () {
        if ($('#pdfviewer-menu').length === 1) {
            $('.left-container, #pdfviewer-menu').toggleClass('d-none');
            $(window).trigger('resize.PdfMainView');
        }
    });
});
