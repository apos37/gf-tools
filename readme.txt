=== Advanced Tools for Gravity Forms ===
Contributors: apos37
Tags: report, spam, merge tags, search, schedule
Requires at least: 5.9.0
Requires PHP: 7.4
Tested up to: 6.6.2
Stable tag: 1.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Unlock advanced tools and customizations to supercharge your Gravity Forms experience with enhanced features and streamlined management.

== Description ==

**Advanced Tools for Gravity Forms** is your go-to solution for enhancing and customizing your Gravity Forms experience. This powerful plugin delivers a rich set of features designed to optimize form management, expand functionality, and tailor forms precisely to your needs. Whether you're looking to streamline your workflow or add cutting-edge capabilities, this plugin is essential for elevating your form game.

**YOU MUST HAVE GRAVITY FORMS INSTALLED TO USE THIS ADD-ON**

== Features ==

=== What's Hot ===
- **Front-End Report Builder:** Create entry reports for the front end.
- **Enhanced Multi-Site Spam Filtering:** Protect your forms with advanced spam prevention across multiple sites.
- **Global Signatures:** Include custom signatures in confirmations and notifications with a merge tag.
- **Merge Tag Dashboard:** Preview merge tag values in an intuitive dashboard.
- **Custom Merge Tags:** Create and use custom merge tags for repetitive information that may change in the future.
- **Entry Management:** Mark entries as resolved, unresolved, or pending for better organization.
- **Scheduled Form Display:** Set specific dates and times for when forms should be visible.
- **Pre-Populate Fields:** Pre-fill form fields with dynamic values such as a list of users or timezones.
- **Search User Entries:** Quickly find and manage entries based on user submission.
- **Duplicate Entry Management:** Automatically remove duplicate entries from the same user.
- **Review Page:** Implement a review step before final submission to ensure accuracy.
- **Admin Field Flexibility:** Disable required fields and pre-populate quiz answers for admin users.

=== Global Settings ===
- **Template Bypass:** Skip the template library when creating new forms for a streamlined process.
- **AJAX Saving Control:** Disable AJAX saving across all forms.
- **Form Editor Optimization:** Remove unnecessary field sections and disable post meta queries in the form editor.
- **Form Tracking:** Keep track of form creation and modification history.
- **Shortcode Action Link:** Quickly copy shortcodes with a convenient action link in the forms table.

=== Form Settings ===
- **Custom Submit Button Options:** Remove the submit button, change to button type, or add custom classes.
- **Post or Page Integration:** Connect forms to posts or pages to auto-populate meta fields.
- **IP Privacy:** Prevent user IP addresses from being saved.
- **Quiz Display:** Show quiz answers in a side panel for easier review.
- **Flexible Email Fields:** Use text and drop-down fields in "Send To" email notification settings.

=== For Developers ===
- **Debug Tools:** Access quick debug views of form and entry objects directly from the toolbar.
- **Log Messages:** Record Gravity Forms messages to the debug log for troubleshooting.
- **Custom Fields:** Add custom fields to form settings for extended functionality.

=== And Many More... ===
- **Extensive Options:** Discover numerous additional features and settings.
- **Community Requests:** Join our Discord server to request new settings and share feedback.

== Third-Party Services ==
This plugin constructs full URLs using the server's HTTP host and request URI. It defaults to `http://localhost` for local development environments.

The plugin is designed to support localhost and does not send data to any external services. Therefore, there are no terms of use or privacy policies applicable to third-party services.

== Installation ==

1. Install the plugin from your website's plugin directory, or upload the plugin to your plugins folder. 
2. Activate it.
3. Go to Gravity Forms > Settings > Advanced Tools.

== Frequently Asked Questions ==

= How do I use the front-end report builder? =
Navigate to Forms > Advanced Tools > Front-End Reports to build your report. Use the `[gfat_report id=""]` shortcode with the report ID in the `id` parameter on the page where you want the report displayed.

= What's the purpose of connecting a form to a post or page? =
You can connect a form to a another post, page, or custom post type to populate meta data into your form. You can use merge tags `{connection:[meta_key]}` to display the data in your confirmations or notifications. To set up, navigate to your form's Advanced Tools settings, and scroll down to Field Population. You can either enter a post ID to connect to a single post for the entire form, or if you want to use the same form for multiple posts, like I do for using a single evaluation across multiple training posts, you can enter a query string parameter instead. Then you would pass the post ID in the URL like: `https://yourdomain.com/your-form-page/?post_id=1`, whereas `post_id` would be the query string parameter. This is useful to combine reports into one, as well as to minimize the number of forms and pages you have to create and manage if they're all going to be the same anyway.

= Can I use the same spam records across multiple sites? =
Yes. Navigate to Forms > Settings > Advanced Tools. Scroll down to the Entries section. Where it says, "Enable Enhanced Spam Filtering," choose "Host" on the host site, and generate a new API Key. On the other sites, choose "Client" and enter the API Key from the host site, along with the URL of the host site. Then on the host site only you need to create a database table where you will store the spam data. To do so, click on "Manage Spam List" from these settings, or navigate to Forms > Advanced Tools > Spam List. **You will only need to create a database table on the host site!** Now, on the client site you can go to the spam list and you should see the list of spam records from the host site and a form where you can add a new record. Records will be saved on the host site's database where all sites will use the same list.

= How do I use the global signatures? =
Navigate to Forms > Settings > Advanced Tools. Scroll down to the Confirmatations and Notifications sections. Create a confirmations signature and/or a notifications signature here. Then use the `{confirmation_signature}` merge tag on the bottom of your confirmations where you want to use the confirmation signature. Likewise, use the `{notification_signature}` merge tag on the notifications where you want to use the notifications signature.

= How do I make custom merge tags? =
Navigate to Forms > Settings > Advanced Tools. Scroll down to the Merge Tags section. Add a new field. Enter a label that you want to use in the merge tag drop downs. Enter a modifier, which will be used in the merge tag itself (ie. `{gfat:[modifier]}`). 

* For a direct value (such as a contact phone number that may change in the future), you can select "Value" and enter the text or numeric value that you want the merge tag to populate.
* For more advanced users, you can select "Callback Function," and include the callback function name. This way you can populate stuff more dynamically. Your function should look like:

`<?php
function callback_name( $form, $entry ) {
    return "your value"; 
}
?>`

= How do I make custom form settings? =
Navigate to Forms > Settings > Advanced Tools. Scroll down to the For Developers section. Add a new field and enter the field label, meta key and field type. The field will then be added to all of your forms' settings. The form setting values are saved on the form object, and can be used in your custom queries.

= Where can I request features and get further support? =
Join my [Discord support server](https://discord.gg/3HnzNEJVnR)

== Screenshots ==
1. Global Search
2. Entries by Date
3. Spam List
4. Report Builder on Back-End
5. Report on Front-End
6. Merge Tags
7. Global Settings 1
8. Global Settings 2
9. Form Settings
10. Entry Debugging

== Changelog ==
= 1.0.2 =
* Created plugin on August 8, 2024