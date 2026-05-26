'use strict';

const express = require('express');
const health = require('../services/health.service');

const router = express.Router();

router.get('/health', (req, res) => {
  res.json(health.snapshot());
});

module.exports = router;
