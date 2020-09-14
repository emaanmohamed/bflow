<?php

/**
 * you can return one of those types:-
 * [
 *  opens, blocks, bounce_drops, bounces, clicks, deferred, delivered, invalid_emails,
 *  processed, requests, spam_report_drops, spam_reports, unique_clicks, unique_opens,
 *  unsubscribe_drops, unsubscribes
 * ]
 *
 * Here's some desc:
 * Blocks               - The number of emails that were not allowed to be delivered by ISPs.
 * Bounces              - The number of emails that bounced instead of being delivered.
 * Clicks               - The number of links that were clicked in your emails.
 * Deliveries           - The number of emails SendGrid was able to confirm were actually delivered to a recipient.
 * Invalid Emails       - The number of recipients that you sent emails to, who had malformed email addresses or whose mail provider reported the address as invalid.
 * Opens                - The total number of times your emails were opened by recipients.
 * Requests             - The number of emails you requested to send via SendGrid.
 * Spam Reports         - The number of recipients who marked your email as spam.
 * Spam Report Drops    - The number of emails dropped by SendGrid because that recipient previously marked your emails as spam.
 * Unique Opens         - The number of unique recipients who opened your emails.
 * Unique Clicks        - The number of unique recipients who clicked links in your emails.
 * Unsubscribes         - The number of recipients who unsubscribed from your emails.
 * Unsubscribe Drops    - The number of emails dropped by SendGrid because the recipient unsubscribed from your emails.
 *
 * @param $state
 * @param $stateIndex
 * @param int $statsIndex
 * @param string $type
 * @return integer
 */
function getStateByType($state, $type, $stateIndex, $statsIndex = 0)
{
    if (isset($state[$stateIndex]) && isset($state[$stateIndex]->stats[$statsIndex])) {
        return $state[$stateIndex]->stats[$statsIndex]->metrics->$type;
    }
}