<?php

// The dashboard's API & Integrations hub (Dashboard\ApiAccessController).
return [

    /*
    |--------------------------------------------------------------------|
    | AffilStack Zapier app invite link
    |--------------------------------------------------------------------|
    |
    | The Zapier app in integrations/zapier is a private integration until
    | it's published to Zapier's App Directory. After `zapier push`, paste
    | its invite link (Zapier developer platform > Manage > Sharing) here
    | and the No-code tab shows an "Open AffilStack on Zapier" button. Left
    | empty, the tab points users at Webhooks by Zapier instead, which works
    | with no app at all.
    |
    */
    'zapier_invite_url' => env('AFFILSTACK_ZAPIER_INVITE_URL'),

];
