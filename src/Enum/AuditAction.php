<?php

declare(strict_types=1);

namespace App\Enum;

enum AuditAction: string
{
    case AdminVendorSuspend = 'AMDIN_VENDOR_SUSPEND';
    case AdminVendorActivate = 'ADMIN_VENDOR_ACTIVATE';
    case AdminProductHide = 'ADMIN_PRODUCT_HIDE';
    case AdminProductPublish = 'ADMIN_PRODUCT_PUBLISH';
    case AdminOrderBlock = 'ADMIN_ORDER_BLOCK';
    case AdminOrderForceStatus = 'ADMIN_ORDER_FORCE_STATUS';
    case AdminOrderRefund = 'ADMIN_ORDER_REFUND';
    case AdminLogsView = 'ADMIN_LOGS_VIEW';
    case AdminLogsDownload = 'ADMIN_LOGS_DOWNLOAD';
    case AdminLogsClear = 'ADMIN_LOGS_CLEAR';
    case AdminTwoFactorReset = 'ADMIN_TWO_FACTOR_RESET';
    case StockLow = 'STOCK_LOW';
    case LoginSuccess = 'LOGIN_SUCCESS';
    case LoginFailure = 'LOGIN_FAILURE';
    case AuditTest = 'AUDIT_TEST';
    case TwoFactorSuccess = 'TWO_FACTOR_SUCCESS';
}
