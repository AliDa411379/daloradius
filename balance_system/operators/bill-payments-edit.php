<?php
/*
 *********************************************************************************************************
 * daloRADIUS - RADIUS Web Platform - BALANCE SYSTEM
 * Payment Editing - DISABLED for Balance System
 * 
 * Editing payments is disabled in the balance system to maintain audit integrity.
 * To correct a payment:
 * 1. Delete the incorrect payment (this will reverse the balance transaction)
 * 2. Create a new payment with correct amount
 *
 *********************************************************************************************************
 */
 
    include("../../../app/operators/library/checklogin.php");
    $operator = $_SESSION['operator_user'];

    include_once('../../../app/operators/lang/main.php");
    include("../../../app/common/includes/layout.php");

    $title = "Edit Payment - DISABLED";
    $help = "Payment editing is disabled in the Balance System to maintain audit trail integrity.";
    
    print_html_prologue($title, $langCode);
    print_title_and_help($title, $help);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            
            <div class="alert alert-warning">
                <h3>⚠️ Payment Editing Disabled</h3>
                <p><strong>Editing payments is disabled in the Balance System</strong> to maintain complete audit trail integrity.</p>
                
                <hr>
                
                <h5>Why is editing disabled?</h5>
                <ul>
                    <li>Each payment transaction affects user balance and is logged in history</li>
                    <li>Editing would create inconsistencies in the audit trail</li>
                    <li>All balance changes must be traceable and immutable</li>
                </ul>
                
                <hr>
                
                <h5>How to correct a payment:</h5>
                <ol>
                    <li><strong>Delete the incorrect payment</strong> - This will automatically reverse the balance transaction</li>
                    <li><strong>Create a new payment</strong> with the correct amount</li>
                    <li>Both transactions will be recorded in the balance history</li>
                </ol>
                
                <hr>
                
                <div class="mt-4">
                    <a href="bill-payments-list.php" class="btn btn-primary">
                        📋 View Payment List
                    </a>
                    <a href="bill-payments-new.php" class="btn btn-success">
                        ➕ Create New Payment
                    </a>
                    <a href="bill-payments-del.php<?php echo isset($_GET['payment_id']) ? '?payment_id=' . intval($_GET['payment_id']) : ''; ?>" class="btn btn-danger">
                        🗑️ Delete Payment
                    </a>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5>Alternative: Balance Adjustments</h5>
                </div>
                <div class="card-body">
                    <p>If you need to adjust a user's balance without affecting invoices, use the <strong>Balance Adjustment</strong> feature:</p>
                    <ul>
                        <li>Add balance credits for refunds or bonuses</li>
                        <li>Deduct balance for corrections or fees</li>
                        <li>All adjustments are recorded with full audit trail</li>
                    </ul>
                    <a href="../api/balance_add.php" class="btn btn-info">
                        💰 Balance Adjustment Tool
                    </a>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php
    include('../../../app/common/includes/layout_footer.php');
?>