<?php

namespace App;

enum PurchaseRequestApprovalDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
