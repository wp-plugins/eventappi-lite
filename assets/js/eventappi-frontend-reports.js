(function() {

    'use strict';

    var $ = jQuery;
    var LANG = eventappi_ajax_obj_reports.text;

    /**
     * EventAppi Reports
     */
    var EAReports = {

        /**
         * ID of the container element
         *
         * @constant {string}
         */
        CONTAINER_ID: 'ea-reports',

        /**
         * Prefix for all element IDs and CSS classes
         *
         * @constant {string}
         */
        PREFIX: 'ea-reports-',

        /**
         * Number of days in a week
         *
         * @constant {number}
         */
        DAYS_PER_WEEK: 7,

        /**
         * Number of weeks in a month (approximately)
         *
         * @constant {number}
         */
        WEEKS_PER_MONTH: 4,

        /**
         * Constant representing the "week" range for a chart
         *
         * @constant {string}
         */
        RANGE_WEEK: 'week',

        /**
         * Constant representing the "month" range for a chart
         *
         * @constant {string}
         */
        RANGE_MONTH: 'month',

        /**
         * Constant representing the "custom" range for a chart
         *
         * @constant {string}
         */
        RANGE_CUSTOM: 'custom',

        /**
         * Reports (keys are report IDs, values are report objects)
         *
         * @type {object}
         */
        reports: {},

        /**
         * ID of the current report
         *
         * @type {string}
         */
        currentReportId: null,

        /**
         * Initialise
         */
        init: function() {

            var reportTabs   = [];
            var reportBodies = [];
            var i, report;

            var reports = [
                new EAReportTicketSales(),
                new EAReportRevenue(),
                new EAReportTicketAvailability()
            ];

            this.currentReportId = reports[0].reportId;

            for (i = 0; i < reports.length; i++) {

                report = reports[i];

                report.init();

                reportTabs.push(report.$reportTab);
                reportBodies.push(report.$reportBody);

                this.reports[report.reportId] = report;

            }

            $('#' + this.CONTAINER_ID).append(
                $('<div></div>').attr('id', this.PREFIX + 'tabs-container').append(reportTabs),
                $('<div></div>').attr('id', this.PREFIX + 'bodies-container').append(reportBodies)
            );

            $.when(

                EAReportsAPI.get('frontend_stats_ticket_sales', this.generateApiParamsTicketSales()),
                EAReportsAPI.get('ticket_stats', {})

            ).done(function(responseTicketSales, responseTicketAvailability) {

                EAReportsSourceData.ticketSales        = responseTicketSales[0];
                EAReportsSourceData.ticketAvailability = responseTicketAvailability[0];

                // The dates returned by the API are strings, so cast them to Date objects
                for (i = 0; i < EAReportsSourceData.ticketSales.dates.length; i++) {
                    EAReportsSourceData.ticketSales.dates[i] = new Date(EAReportsSourceData.ticketSales.dates[i]);
                }

                for (i = 0; i < reports.length; i++) {
                    reports[i].run();
                }

                EAReports.changeCurrentReport('ticket-sales');

            });

        },

        /**
         * Generate the parameters for the "frontend_stats_ticket_sales" API call
         *
         * @return {object}
         */
        generateApiParamsTicketSales: function() {

            var dateNow        = new Date();
            var dateOneYearAgo = new Date(dateNow.getFullYear() - 1, dateNow.getMonth(), dateNow.getDate());

            return {
                date_start: EAUtils.formatDateISO(dateOneYearAgo),
                date_end:   EAUtils.formatDateISO(dateNow)
            };

        },

        /**
         * Change to the specified report
         *
         * @param {string} newReportId
         */
        changeCurrentReport: function(newReportId) {

            var currentReport = this.reports[this.currentReportId];
            var newReport     = this.reports[newReportId];

            EAUtils.deactivateTab(currentReport.$reportTab);
            EAUtils.activateTab(newReport.$reportTab);

            currentReport.$reportBody.hide();
            newReport.$reportBody.show();

            this.currentReportId = newReportId;

        },

        /**
         * Get the label text for the specified range
         *
         * @param  {string} range - One of the EAReports.RANGE_* constants
         * @return {string}
         */
        getRangeLabelText: function(range) {

            switch (range) {
                case EAReports.RANGE_WEEK:
                    return LANG.week;
                case EAReports.RANGE_MONTH:
                    return LANG.month;
                case EAReports.RANGE_CUSTOM:
                    return LANG.custom;
            }

        }

    };

    /**
     * EventAppi Reports: API
     */
    var EAReportsAPI = {

        /**
         * Perform a GET request
         *
         * eventappi_ajax_obj_reports is created by wp_localize_script() in ChirrpyPublic::public_ajaxurl()
         *
         * @param  {string} action
         * @param  {object} params
         * @return {object} - jQuery Deferred Ajax object
         */
        get: function(action, params) {

            var data = {
                'action':      eventappi_ajax_obj_reports.plugin_name + '_' + action,
                '_ajax_nonce': eventappi_ajax_obj_reports.nonce
            };

            for (var p in params) {
                if (params.hasOwnProperty(p)) {
                    data[p] = params[p];
                }
            }

            return $.ajax({
                url:      eventappi_ajax_obj_reports.ajax_url,
                method:   'GET',
                data:     data,
                dataType: 'json'
            });

       }

    };

    /**
     * EventAppi Reports: Source Data
     */
    var EAReportsSourceData = {

        /**
         * Data returned from the "frontend_stats_ticket_sales" API call
         *
         * {
         *     "dates": [
         *         "2015-03-17",
         *         ...
         *     ],
         *     "events": [
         *         {
         *             "id": 4,
         *             "name": "My Event",
         *             "ticketsSoldPerDate": [
         *                 25,  // Number of tickets sold on 2015-03-17
         *                 ...
         *             ],
         *             "totalRevenuePerDate": [
         *                 75000,  // Revenue (in pence) from tickets sold on 2015-03-17
         *                 ...
         *             ]
         *         },
         *         ...
         *     ]
         * }
         *
         * @type {object}
         */
        ticketSales: {},

        /**
         * Data returned from the "ticket_stats" API call
         *
         * [
         *     {
         *         "id": 4,
         *         "name": "My Event",
         *         "tickets_available": 500,  // Total tickets created for the event
         *         "tickets_sold": 156
         *     },
         *     ...
         * ]
         *
         * @type {array}
         */
        ticketAvailability: []

    };

    /**
     * EACustomRangeSelector constructor
     *
     * @param {Date}     defaultDate
     * @param {function} changeCallback
     * @class
     */
    function EACustomRangeSelector(defaultDate, changeCallback) {

        defaultDate = EAUtils.formatDateISO(defaultDate);

        this.currentDate = defaultDate;  // ISO 8601 format (YYYY-MM-DD)

        var _this = this;

        var dpOptions = {
            changeMonth: true,
            changeYear:  true,
            dateFormat:  'yy-mm-dd',  // ISO 8601, compatible with EAUtils.formatDateISO()
            defaultDate: defaultDate,
            maxDate:     new Date(),
            onSelect:    function(dateText) {
                _this.setDate(dateText);
                _this.$datepicker.hide();
                changeCallback();
            }
        };

        this.$display =
            $('<div></div>')
                .on('click', function() {
                    _this.$datepicker.show();
                    _this.$datepicker.datepicker(dpOptions);
                });

        this.$datepicker =
            $('<div></div>')
                .hide();

        this.$container =
            $('<div></div>')
                .append(
                    this.$display,
                    this.$datepicker
                );

        this.setDate(defaultDate);

    }

    /**
     * EACustomRangeSelector: set date
     *
     * @param {string} date - ISO 8601 format (YYYY-MM-DD)
     */
    EACustomRangeSelector.prototype.setDate = function(date) {

        this.currentDate = date;

        this.$datepicker.datepicker('setDate', date);

        this.$display.text(EAUtils.formatDatePretty(new Date(date), true));

    };

    /**
     * EACustomRangeSelector: get date
     *
     * @return {Date}
     */
    EACustomRangeSelector.prototype.getDate = function() {

        return new Date(this.currentDate);

    };

    /**
     * EventAppi Report: Abstract parent class
     *
     * @param {string} id
     * @param {string} name
     * @class
     * @abstract
     */
    function EAReportAbstract(id, name) {

        this.reportId   = id;
        this.reportName = name;

        /**
         * The current range
         *
         * @var {string} - One of the EAReports.RANGE_* constants
         */
        this.currentRange = EAReports.RANGE_WEEK;

        /**
         * The report's tab
         *
         * @var {object} - jQuery DOM element
         */
        this.$reportTab = null;

        /**
         * The report's body
         *
         * @var {object} - jQuery DOM element
         */
        this.$reportBody = null;

        /**
         * The report's chart container
         *
         * @var {object} - jQuery DOM element
         */
        this.$chartContainer = null;

        /**
         * The range subtabs (keys are EAReports.RANGE_* constants, values are jQuery DOM elements)
         *
         * @var {object}
         */
        this.rangeSubtabs = {};

        /**
         * The custom range controls
         *
         * @var {object} - jQuery DOM element
         */
        this.$customRangeControls = null;

        /**
         * Selector for the custom range start date
         *
         * @var {EACustomRangeSelector}
         */
        this.customRangeSelectorStart = null;

        /**
         * Selector for the custom range end date
         *
         * @var {EACustomRangeSelector}
         */
        this.customRangeSelectorEnd = null;

    }

    /**
     * EAReportAbstract: Create DOM elements
     *
     * @param {boolean} rangeSubtabsRequired
     */
    EAReportAbstract.prototype.createDomElements = function(rangeSubtabsRequired) {

        var _this = this;

        this.$reportTab =
            $('<div></div>')
                .text(this.reportName)
                .on('click', function() {
                    EAReports.changeCurrentReport(_this.reportId);
                });

        this.$reportBody = $('<div></div>');

        if (rangeSubtabsRequired) {
            this.$reportBody.append(
                this.createRangeSubtabs(),
                this.createCustomRangeControls()
            );
        }

        this.$chartContainer = $('<div></div>');

        this.$reportBody.append(this.$chartContainer);

    };

    /**
     * EAReportAbstract: Create the range subtabs element
     *
     * @return {object} - jQuery DOM element
     */
    EAReportAbstract.prototype.createRangeSubtabs = function() {

        var $rangeSubtabs =
            $('<div></div>')
                .addClass(EAReports.PREFIX + 'range-subtabs-container')
                .append(
                    this.createRangeSubtab(EAReports.RANGE_WEEK),
                    this.createRangeSubtab(EAReports.RANGE_MONTH),
                    this.createRangeSubtab(EAReports.RANGE_CUSTOM)
                );

        return $rangeSubtabs;

    };

    /**
     * EAReportAbstract: Create a range subtab element
     *
     * @param  {string} range - One of the EAReports.RANGE_* constants
     * @return {object} - jQuery DOM element
     */
    EAReportAbstract.prototype.createRangeSubtab = function(range) {

        var _this = this;

        var $rangeSubtab =
            $('<div></div>')
                .text(EAReports.getRangeLabelText(range))
                .on('click', function() {
                    _this.changeCurrentRange(range);
                });

        this.rangeSubtabs[range] = $rangeSubtab;

        return $rangeSubtab;

    };

    /**
     * EAReportAbstract: Create custom range controls
     *
     * @return {object} - jQuery DOM element
     */
    EAReportAbstract.prototype.createCustomRangeControls = function() {

        var _this = this;

        var changeCallback = function() {
            _this.renderChart(EAReports.RANGE_CUSTOM);
        };

        var dateNow         = new Date();
        var dateOneMonthAgo = new Date(dateNow.getFullYear(), dateNow.getMonth() - 1, dateNow.getDate());

        this.customRangeSelectorStart = new EACustomRangeSelector(dateOneMonthAgo, changeCallback);
        this.customRangeSelectorEnd   = new EACustomRangeSelector(dateNow, changeCallback);

        this.$customRangeControls =
            $('<div></div>')
                .addClass(EAReports.PREFIX + 'custom-range-container')
                .append(
                    this.customRangeSelectorStart.$container,
                    $('<span></span>').text('to'),
                    this.customRangeSelectorEnd.$container
                );

        return this.$customRangeControls;

    };

    /**
     * EAReportAbstract: Change the current range
     *
     * @param {string} newRange - One of the EAReports.RANGE_* constants
     */
    EAReportAbstract.prototype.changeCurrentRange = function(newRange) {

        EAUtils.deactivateTab(this.rangeSubtabs[this.currentRange]);
        EAUtils.activateTab(this.rangeSubtabs[newRange]);

        if (this.$customRangeControls !== null) {
            this.$customRangeControls.css(
                'display',
                (newRange === EAReports.RANGE_CUSTOM) ? 'inline-block' : 'none'
            );
        }

        this.renderChart(newRange);

        this.currentRange = newRange;

    };

    /**
     * EAReportAbstract: Initialise
     *
     * @abstract
     */
    EAReportAbstract.prototype.init = function() {

        throw new Error('EAReportAbstract.init() is abstract, and must be implemented by a subclass');

    };

    /**
     * EAReportAbstract: Run
     *
     * @abstract
     */
    EAReportAbstract.prototype.run = function() {

        throw new Error('EAReportAbstract.run() is abstract, and must be implemented by a subclass');

    };

    /**
     * EAReportAbstract: Render the chart for the specified range
     *
     * @param {string} range - One of the EAReports.RANGE_* constants
     * @abstract
     */
    EAReportAbstract.prototype.renderChart = function(range) {

        throw new Error('EAReportAbstract.renderChart() is abstract, and must be implemented by a subclass');

    };

    /**
     * EventAppi Report: Ticket Sales
     *
     * @class
     * @extends EAReportAbstract
     */
    function EAReportTicketSales() {

        // Call parent constructor
        EAReportAbstract.call(
            this,             // object to be used by parent constructor for "this"
            'ticket-sales',   // id
            LANG.ticket_sales // name
        );

    }

    // Inheritance
    EAReportTicketSales.prototype = Object.create(EAReportAbstract.prototype);

    /**
     * EAReportTicketSales: Initialise
     *
     * @implements EAReportAbstract.init()
     */
    EAReportTicketSales.prototype.init = function() {

        var rangeSubtabsRequired = true;

        this.createDomElements(rangeSubtabsRequired);

    };

    /**
     * EAReportTicketSales: Run
     *
     * @implements EAReportAbstract.run()
     */
    EAReportTicketSales.prototype.run = function() {

        this.changeCurrentRange(EAReports.RANGE_WEEK);

    };

    /**
     * EAReportTicketSales: Generate common chart data which is needed for every range
     *
     * @return {object}
     */
    EAReportTicketSales.prototype.generateCommonChartData = function() {

        var sourceData = EAReportsSourceData.ticketSales;

        var chartData = {
            categories: [],
            series:     []
        };

        var e;

        for (e = 0; e < sourceData.events.length; e++) {
            chartData.series.push({
                name: sourceData.events[e].name,
                data: []
            });
        }

        return chartData;

    };

    /**
     * EAReportTicketSales: Generate chart data for the "week" range
     *
     * @return {object}
     */
    EAReportTicketSales.prototype.generateChartDataForWeek = function() {

        var sourceData = EAReportsSourceData.ticketSales;
        var chartData  = this.generateCommonChartData();
        var dayOffset, dayIndex, date, e, ticketsSold;

        for (dayOffset = 0; dayOffset < EAReports.DAYS_PER_WEEK; dayOffset++) {

            dayIndex = (sourceData.dates.length - EAReports.DAYS_PER_WEEK) + dayOffset;
            date     = sourceData.dates[dayIndex];

            chartData.categories.push(
                EAUtils.formatDatePretty(date)
            );

            for (e = 0; e < sourceData.events.length; e++) {
                ticketsSold = sourceData.events[e].ticketsSoldPerDate[dayIndex];
                chartData.series[e].data.push(ticketsSold);
            }

        }

        return chartData;

    };

    /**
     * EAReportTicketSales: Generate chart data for the "month" range
     *
     * @return {object}
     */
    EAReportTicketSales.prototype.generateChartDataForMonth = function() {

        var sourceData = EAReportsSourceData.ticketSales;
        var chartData  = this.generateCommonChartData();
        var firstDay   = sourceData.dates.length - (EAReports.DAYS_PER_WEEK * EAReports.WEEKS_PER_MONTH);
        var week, startDay, endDay, startDate, endDate, e, ticketsSold, dayOffset, dayIndex;

        for (week = 0; week < EAReports.WEEKS_PER_MONTH; week++) {

            startDay = firstDay + (week * EAReports.DAYS_PER_WEEK);
            endDay   = startDay + EAReports.DAYS_PER_WEEK - 1;

            startDate = sourceData.dates[startDay];
            endDate   = sourceData.dates[endDay];

            chartData.categories.push(
                EAUtils.formatDatePretty(startDate) + ' - ' +
                EAUtils.formatDatePretty(endDate)
            );

            for (e = 0; e < sourceData.events.length; e++) {
                ticketsSold = 0;
                for (dayOffset = 0; dayOffset < EAReports.DAYS_PER_WEEK; dayOffset++) {
                    dayIndex = startDay + dayOffset;
                    ticketsSold += sourceData.events[e].ticketsSoldPerDate[dayIndex];
                }
                chartData.series[e].data.push(ticketsSold);
            }

        }

        return chartData;

    };

    /**
     * EAReportTicketSales: Generate chart data for the "custom" range
     *
     * @return {object}
     */
    EAReportTicketSales.prototype.generateChartDataForCustomRange = function() {

        var sourceData = EAReportsSourceData.ticketSales;
        var chartData  = this.generateCommonChartData();
        var i, date, e, ticketsSold;

        var dateStart = this.customRangeSelectorStart.getDate();
        var dateEnd   = this.customRangeSelectorEnd.getDate();

        for (i = 0; i < sourceData.dates.length; i++) {

            date = sourceData.dates[i];

            if (date.getTime() < dateStart.getTime()) {
                continue;
            }

            if (date.getTime() > dateEnd.getTime()) {
                continue;
            }

            chartData.categories.push(
                EAUtils.formatDatePretty(date)
            );

            for (e = 0; e < sourceData.events.length; e++) {
                ticketsSold = sourceData.events[e].ticketsSoldPerDate[i];
                chartData.series[e].data.push(ticketsSold);
            }

        }

        return chartData;

    };

    /**
     * EAReportTicketSales: Render the chart for the specified range
     *
     * @param {string} range - One of the EAReports.RANGE_* constants
     * @implements EAReportAbstract.renderChart()
     */
    EAReportTicketSales.prototype.renderChart = function(range) {

        var chartData;
        var chartTitle;

        switch (range) {
            case EAReports.RANGE_WEEK:
                chartData  = this.generateChartDataForWeek();
                chartTitle = LANG.tickets_sold_range_week;
                break;
            case EAReports.RANGE_MONTH:
                chartData  = this.generateChartDataForMonth();
                chartTitle = LANG.tickets_sold_range_month;
                break;
            case EAReports.RANGE_CUSTOM:
                chartData  = this.generateChartDataForCustomRange();
                chartTitle = LANG.tickets_sold_range_day;
                break;
            default:
                return;
        }

        this.$chartContainer.html('').highcharts({
            chart: {
                type: 'column',
                borderColor: '#C0C0C0',  // Same as yAxis.gridLineColor
                borderWidth: 1,
                width: EAUtils.getReportsContainerWidth()
            },
            title: {
                text: chartTitle
            },
            xAxis: {
                categories: chartData.categories
            },
            yAxis: {
                min: 0,
                title: {
                    text: LANG.tickets_sold
                }
            },
            series: chartData.series,
            tooltip: {
                pointFormat: '{series.name}: <b>{point.y}</b><br/>'
            },
            plotOptions: {
                series: {
                    animation: {
                        duration: 250  // Milliseconds
                    }
                }
            }
        });

    };

    /**
     * EventAppi Report: Revenue
     *
     * @class
     * @extends EAReportAbstract
     */
    function EAReportRevenue() {

        // Call parent constructor
        EAReportAbstract.call(
            this,       // object to be used by parent constructor for "this"
            'revenue',  // id
            'Revenue'   // name
        );

    }

    // Inheritance
    EAReportRevenue.prototype = Object.create(EAReportAbstract.prototype);

    /**
     * EAReportRevenue: Initialise
     *
     * @implements EAReportAbstract.init()
     */
    EAReportRevenue.prototype.init = function() {

        var rangeSubtabsRequired = true;

        this.createDomElements(rangeSubtabsRequired);

    };

    /**
     * EAReportRevenue: Run
     *
     * @implements EAReportAbstract.run()
     */
    EAReportRevenue.prototype.run = function() {

        this.changeCurrentRange(EAReports.RANGE_WEEK);

    };

    /**
     * EAReportRevenue: Generate common chart data which is needed for every range
     *
     * @return {object}
     */
    EAReportRevenue.prototype.generateCommonChartData = function() {

        var sourceData = EAReportsSourceData.ticketSales;

        var chartData = {
            categories: [LANG.events],
            series:     []
        };

        var e;

        for (e = 0; e < sourceData.events.length; e++) {
            chartData.series.push({
                name: sourceData.events[e].name,
                data: []
            });
        }

        return chartData;

    };

    /**
     * EAReportRevenue: Generate chart data for the "week" range
     *
     * @return {object}
     */
    EAReportRevenue.prototype.generateChartDataForWeek = function() {

        var sourceData = EAReportsSourceData.ticketSales;
        var chartData  = this.generateCommonChartData();
        var e, revenue, dayOffset, dayIndex;

        for (e = 0; e < sourceData.events.length; e++) {

            revenue = 0;

            for (dayOffset = 0; dayOffset < EAReports.DAYS_PER_WEEK; dayOffset++) {

                dayIndex = (sourceData.dates.length - EAReports.DAYS_PER_WEEK) + dayOffset;
                revenue += (sourceData.events[e].totalRevenuePerDate[dayIndex] / 100);

            }

            chartData.series[e].data.push(Math.floor(revenue));

        }

        return chartData;

    };

    /**
     * EAReportRevenue: Generate chart data for the "month" range
     *
     * @return {object}
     */
    EAReportRevenue.prototype.generateChartDataForMonth = function() {

        var sourceData     = EAReportsSourceData.ticketSales;
        var chartData      = this.generateCommonChartData();
        var DAYS_PER_MONTH = EAReports.DAYS_PER_WEEK * EAReports.WEEKS_PER_MONTH;
        var e, revenue, dayOffset, dayIndex;

        for (e = 0; e < sourceData.events.length; e++) {

            revenue = 0;

            for (dayOffset = 0; dayOffset < DAYS_PER_MONTH; dayOffset++) {

                dayIndex = (sourceData.dates.length - DAYS_PER_MONTH) + dayOffset;
                revenue += (sourceData.events[e].totalRevenuePerDate[dayIndex] / 100);

            }

            chartData.series[e].data.push(Math.floor(revenue));

        }

        return chartData;

    };

    /**
     * EAReportRevenue: Generate chart data for the "custom" range
     *
     * @return {object}
     */
    EAReportRevenue.prototype.generateChartDataForCustomRange = function() {

        var sourceData = EAReportsSourceData.ticketSales;
        var chartData  = this.generateCommonChartData();
        var e, revenue, i, date;

        var dateStart = this.customRangeSelectorStart.getDate();
        var dateEnd   = this.customRangeSelectorEnd.getDate();

        for (e = 0; e < sourceData.events.length; e++) {

            revenue = 0;

            for (i = 0; i < sourceData.dates.length; i++) {

                date = sourceData.dates[i];

                if (date.getTime() < dateStart.getTime()) {
                    continue;
                }

                if (date.getTime() > dateEnd.getTime()) {
                    continue;
                }

                revenue += (sourceData.events[e].totalRevenuePerDate[i] / 100);

            }

            chartData.series[e].data.push(Math.floor(revenue));

        }

        return chartData;

    };

    /**
     * EAReportRevenue: Render the chart for the specified range
     *
     * @param {string} range - One of the EAReports.RANGE_* constants
     * @implements EAReportAbstract.renderChart()
     */
    EAReportRevenue.prototype.renderChart = function(range) {

        var chartData;
        var chartTitle;

        switch (range) {
            case EAReports.RANGE_WEEK:
                chartData  = this.generateChartDataForWeek();
                chartTitle = LANG.revenue_range_week;
                break;
            case EAReports.RANGE_MONTH:
                chartData  = this.generateChartDataForMonth();
                chartTitle = LANG.revenue_range_month;
                break;
            case EAReports.RANGE_CUSTOM:
                chartData  = this.generateChartDataForCustomRange();
                chartTitle = LANG.revenue_range_custom;
                break;
            default:
                return;
        }

        this.$chartContainer.html('').highcharts({
            chart: {
                type: 'column',
                borderColor: '#C0C0C0',  // Same as yAxis.gridLineColor
                borderWidth: 1,
                width: EAUtils.getReportsContainerWidth()
            },
            title: {
                text: chartTitle
            },
            xAxis: {
                categories: chartData.categories
            },
            yAxis: {
                min: 0,
                title: {
                    text: LANG.revenue_in_currency
                }
            },
            series: chartData.series,
            tooltip: {
                pointFormat: '{series.name}: <b>${point.y}</b><br/>'
            },
            plotOptions: {
                series: {
                    animation: {
                        duration: 250  // Milliseconds
                    }
                }
            }
        });

    };

    /**
     * EventAppi Report: Ticket Availability
     *
     * @class
     * @extends EAReportAbstract
     */
    function EAReportTicketAvailability() {

        // Call parent constructor
        EAReportAbstract.call(
            this,                   // object to be used by parent constructor for "this"
            'ticket-availability',  // id
            LANG.ticket_availability   // name
        );

    }

    // Inheritance
    EAReportTicketAvailability.prototype = Object.create(EAReportAbstract.prototype);

    /**
     * EAReportTicketAvailability: Initialise
     *
     * @implements EAReportAbstract.init()
     */
    EAReportTicketAvailability.prototype.init = function() {

        var rangeSubtabsRequired = false;

        this.createDomElements(rangeSubtabsRequired);

    };

    /**
     * EAReportTicketAvailability: Run
     *
     * @implements EAReportAbstract.run()
     */
    EAReportTicketAvailability.prototype.run = function() {

        this.renderChart();

    };

    /**
     * EAReportTicketAvailability: Generate chart data
     *
     * @return {object}
     */
    EAReportTicketAvailability.prototype.generateChartData = function() {

        var sourceData = EAReportsSourceData.ticketAvailability;

        var chartData = {
            categories: [LANG.events],
            series:     []
        };

        var e, sourceEvent, availableTickets;

        for (e = 0; e < sourceData.length; e++) {

            sourceEvent      = sourceData[e];
            availableTickets = sourceEvent.tickets_available - sourceEvent.tickets_sold;

            chartData.series.push({
                name: sourceEvent.name,
                data: [availableTickets]
            });

        }

        return chartData;

    };

    /**
     * EAReportTicketAvailability: Render the chart
     *
     * @implements EAReportAbstract.renderChart()
     */
    EAReportTicketAvailability.prototype.renderChart = function() {

        var chartData = this.generateChartData();

        this.$chartContainer.html('').highcharts({
            chart: {
                type: 'column',
                borderColor: '#C0C0C0',  // Same as yAxis.gridLineColor
                borderWidth: 1,
                width: EAUtils.getReportsContainerWidth()
            },
            title: {
                text: LANG.ticket_availability_per_event
            },
            xAxis: {
                categories: chartData.categories
            },
            yAxis: {
                min: 0,
                title: {
                    text: LANG.tickets_available
                }
            },
            series: chartData.series,
            tooltip: {
                pointFormat: '{series.name}: <b>{point.y}</b><br/>'
            },
            plotOptions: {
                series: {
                    animation: {
                        duration: 250  // Milliseconds
                    }
                }
            }
        });

    };

    /**
     * EventAppi utilities
     */
    var EAUtils = {

        /**
         * Get the width of the reports container
         *
         * @return {number}
         */
        getReportsContainerWidth: function() {

            return $('#' + EAReports.CONTAINER_ID).width();

        },

        /**
         * Activate tab
         *
         * @param {object} $tabElement - jQuery DOM element
         */
        activateTab: function($tabElement) {

            $tabElement.addClass(EAReports.PREFIX + 'active');

        },

        /**
         * Deactivate tab
         *
         * @param {object} $tabElement - jQuery DOM element
         */
        deactivateTab: function($tabElement) {

            $tabElement.removeClass(EAReports.PREFIX + 'active');

        },

        /**
         * Format a date object as a string (pretty)
         *
         * @param  {Date}    date
         * @param  {boolean} [showYear=false]
         * @return {string}
         */
        formatDatePretty: function(date, showYear) {

            var monthNames = [LANG.jan, LANG.feb, LANG.mar, LANG.apr, LANG.may, LANG.jun, LANG.jul, LANG.aug, LANG.sep, LANG.oct, LANG.nov, LANG.dec];

            var day   = date.getDate();
            var month = date.getMonth();

            var formattedDate = day + ' ' + monthNames[month];

            if (showYear === true) {
                formattedDate += ' ' + date.getFullYear();
            }

            return formattedDate;

        },

        /**
         * Format a date object as a string (ISO 8601)
         *
         * @param  {Date} date
         * @return {string}
         */
        formatDateISO: function(date) {

            date = date.toISOString();     // YYYY-MM-DDTHH:mm:ss.sssZ
            date = date.substring(0, 10);  // YYYY-MM-DD

            return date;

        }

    };

    $(document).ready(function() {
        if ($('#eventappi-wrapper').length === 1 && $('#' + EAReports.CONTAINER_ID).length === 1) {
            EAReports.init();
        }
    });

})();
