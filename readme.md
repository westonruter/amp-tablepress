# AMP TablePress

This is an experimental adapter plugin for WordPress to make the [TablePress plugin](https://wordpress.org/plugins/tablepress/) compatible with [AMP](https://amp.dev/) when used with the official [AMP plugin](https://github.com/ampproject/amp-wp/). Instead of using [jQuery DataTables](https://datatables.net/) as normally provided by TablePress, this plugin uses a [fork of Simple-DataTables](https://github.com/westonruter/AMP-Script-Simple-DataTables) via [`amp-script`](https://amp.dev/documentation/components/amp-script/) on AMP pages. The AMP plugin must be in either Standard or Transitional modes; support for the AMP plugin's Reader mode has not been implemented.

To install from source, be sure to `npm install`. To create a plugin ZIP, run `npm run build`.

Use with the latest development version of the AMP plugin (especially changes in [ampproject/amp-wp#3034](https://github.com/ampproject/amp-wp/pull/3034)). Performance may be degraded until [ampproject/worker-dom#609](https://github.com/ampproject/worker-dom/pull/609) is live. In the immediate term, you may need to run `AMP.toggleExperiment('amp-script')` in your browser console until the origin trial is over.
