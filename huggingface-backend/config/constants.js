'use strict';

module.exports = {
  EVENTS: {
    ENGINE_QR: 'engine:qr',
    ENGINE_READY: 'engine:ready',
    ENGINE_AUTHENTICATED: 'engine:authenticated',
    ENGINE_DISCONNECTED: 'engine:disconnected',
    ENGINE_LOADING: 'engine:loading',
    MESSAGE_INBOUND: 'message:inbound',
    MESSAGE_OUTBOUND: 'message:outbound',
    MESSAGE_STATUS: 'message:status',
    LEAD_REPLIED: 'lead:replied',
    LEAD_VALIDATED: 'lead:validated',
    CAMPAIGN_PROGRESS: 'campaign:progress',
    SYSTEM_HEARTBEAT: 'system:heartbeat',
    QUEUE_TICK: 'queue:tick',
  },
  WEBHOOK_TYPES: {
    INBOUND_MESSAGE: 'inbound_message',
    OUTBOUND_STATUS: 'outbound_status',
    QR_UPDATED: 'qr_updated',
    CONNECTION_STATE: 'connection_state',
    NUMBER_VALIDATED: 'number_validated',
    ENGINE_HEALTH: 'engine_health',
  },
  CONNECTION_STATES: {
    INITIALIZING: 'initializing',
    QR: 'qr',
    AUTHENTICATED: 'authenticated',
    READY: 'ready',
    DISCONNECTED: 'disconnected',
    AUTH_FAILURE: 'auth_failure',
  },
};
