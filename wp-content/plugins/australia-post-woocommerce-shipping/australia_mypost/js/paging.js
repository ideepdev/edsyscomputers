(function(jQuery) {
    jQuery(function() {
        jQuery.widget("zpd.paging", {
            options: {
                limit: 5,
                rowDisplayStyle: 'block',
                activePage: 0,
                rows: []
            },
            _create: function() {
                var rows = jQuery("tbody", this.element).children();
                this.options.rows = rows;
                this.options.rowDisplayStyle = rows.css('display');
                var nav = this._getNavBar();
                this.element.before(nav);
                this.showPage(0);
            },
            _getNavBar: function() {
                var rows = this.options.rows;
                var nav = jQuery('<div>', {class: 'paging-nav', id: 'elex_auspost_manifest_paging_nav'});
                for (var i = 0; i < Math.ceil(rows.length / this.options.limit); i++) {
                    this._on(jQuery('<a>', {
                        href: '#',
                        text: (i + 1),
                        "data-page": (i)
                    }).appendTo(nav),
                            {click: "pageClickHandler"});
                }
                //create previous link
                this._on(jQuery('<a>', {
                    href: '#',
                    text: '<<',
                    "data-direction": -1
                }).prependTo(nav),
                        {click: "pageStepHandler"});
                //create next link
                this._on(jQuery('<a>', {
                    href: '#',
                    text: '>>',
                    "data-direction": +1
                }).appendTo(nav),
                        {click: "pageStepHandler"});
                return nav;
            },
            showPage: function(pageNum) {
                var num = pageNum * 1; //it has to be numeric
                this.options.activePage = num;
                var rows = this.options.rows;
                var limit = this.options.limit;
                for (var i = 0; i < rows.length; i++) {
                    if (i >= limit * num && i < limit * (num + 1)) {
                        jQuery(rows[i]).css('display', this.options.rowDisplayStyle);
                    } else {
                        jQuery(rows[i]).css('display', 'none');
                    }
                }
            },
            pageClickHandler: function(event) {
                event.preventDefault();
                jQuery(event.target).siblings().attr('class', "");
                jQuery(event.target).attr('class', "selected-page");
                var pageNum = jQuery(event.target).attr('data-page');
                this.showPage(pageNum);
            },
            pageStepHandler: function(event) {
                event.preventDefault();
                //get the direction and ensure it's numeric
                var dir = jQuery(event.target).attr('data-direction') * 1;
                var pageNum = this.options.activePage + dir;
                //if we're in limit, trigger the requested pages link
                if (pageNum >= 0 && pageNum < this.options.rows.length) {
                    jQuery("a[data-page=" + pageNum + "]", jQuery(event.target).parent()).click();
                }
            }
        });
    });
})(jQuery);