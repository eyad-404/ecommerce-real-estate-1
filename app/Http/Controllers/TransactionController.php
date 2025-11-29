<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Notifications\NewPurchaseRequest;
use App\Services\Logger;

class TransactionController extends Controller
{
    // List all transactions
    public function index()
    {
        try {
            $transactions = Transaction::with(['property', 'buyer', 'seller'])->get();
            return view('transactions.index', compact('transactions'));
        } catch (\Exception $e) {
            return back()->with('error', 'Something went wrong: '.$e->getMessage());
        }
    }

    // Show payment plans for a property
    public function show(Property $property)
    {
        try {
            // Pass the property to the payment plans view
            return view('properties.payment_plans', compact('property'));
        } catch (\Exception $e) {
            return back()->with('error', 'Something went wrong: '.$e->getMessage());
        }
    }

    // Buy property and create transaction
    public function buyProperty(Property $property)
    {
        try {
            $userId = auth()->id(); 

            if ($property->status != 'available') {
                return redirect()->back()->with('error', 'Property is not available for sale.');
            }

            $transaction = Transaction::create([
                'property_id' => $property->id,
                'buyer_id' => $userId,
                'seller_id' => $property->user_id,
                'price' => $property->price,
                'status' => 'pending',
            ]);

            $property->update(['status' => 'pending']);

            // Notify admins
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new NewPurchaseRequest($transaction));
            }

            // Notify seller if client
            $seller = $property->user;
            if($seller && $seller->role === 'client'){
                $seller->notify(new NewPurchaseRequest($transaction));
            }

            return redirect()->route('transactions.show', $transaction)
                             ->with('success', 'Purchase request created successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Something went wrong: '.$e->getMessage());
        }
    }

    public function process(Property $property, $plan)
{
    $downPaymentRate = 0.10;
    $downPayment = $property->price * $downPaymentRate;
    $remainingAmount = $property->price - $downPayment;

    if ($plan === 'annual') {
        $installments = $property->installment_years;
        $installmentAmount = $remainingAmount / $installments;
        $planText = "Annual Installment";
    } elseif ($plan === 'quarterly') {
        $installments = $property->installment_years * 4;
        $installmentAmount = $remainingAmount / $installments;
        $planText = "Quarterly Installment";
    } else { // cash
        $installments = 1;
        $installmentAmount = $property->price;
        $planText = "Full Cash Payment";
    }

    return view('properties.payment', compact(
        'property', 
        'plan', 
        'downPayment', 
        'installments', 
        'installmentAmount', 
        'planText'
    ));
}


public function submitPayment(Request $request, Property $property, $plan)
{
    // Here you handle the payment, store transaction info, etc.
}

public function reserve(Property $property)
{
    $logger = \App\Services\Logger::getInstance();
    $user = auth()->user();

    try {
    if ($property->is_reserved) {
            $logger->warning('Property reservation failed - already reserved', [
                'property_id' => $property->id,
                'user_id' => $user->id,
            ]);

            return back()->with('error', 'This property is already reserved.');
        }

    $transaction = Transaction::create([
        'property_id' => $property->id,
        'buyer_id' => $user->id,
        'seller_id' => $property->user_id,
        'price' => $property->price,
        'status' => 'pending'
    ]);

    // Update property as reserved
    $property->update([
        'status' => 'reserved',
        'reserved_by' => $user->id,
        'is_reserved' => true
    ]);

    // **Assign and save directly**
    $user->reserved_property_id = $property->id;
    $user->save();

    // Send confirmation email (optional: use queue to avoid blocking)
    \Mail::to($user->email)->send(new \App\Mail\PropertyReserved($property));

        $logger->info('Property reserved successfully', [
            'property_id' => $property->id,
            'user_id' => $user->id,
            'transaction_id' => $transaction->id,
        ]);
        
    return back()->with('success', 'Reservation successful! Please check your email.');
    } catch (\Exception $e) {
        $logger->error('Property reservation failed', [
            'property_id' => $property->id,
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);

        return back()->with('error', 'Failed to reserve property. Please try again.');
    }
}





}
