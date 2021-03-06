<?php

namespace LimeDeck\NovaCashierOverview\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Subscription;
use Stripe\Plan;
use Stripe\Subscription as StripeSubscription;

class StripeSubscriptionsController extends Controller
{
    /**
     * @param $subscriptionId
     * @return array
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function show($subscriptionId)
    {
        $subscription = $this->getSubscription($subscriptionId);

        if (!$subscription) {
            return [
                'subscription' => null,
            ];
        }

        return [
            'subscription'    => $this->formatSubscription($subscription),
            'plans'           => $this->formatPlans(Plan::all(['limit' => 100])),
            'invoices'        => $this->formatInvoices($subscription->owner->invoicesIncludingPending()),
            'methods' => $this->formatPaymentMethods($subscription->owner->paymentMethods()->map(function (
                $paymentMethod
            ) {
                return $paymentMethod->asStripePaymentMethod();
            })),
        ];
    }

    /**
     * @param $subscriptionId
     * @return \Illuminate\Http\JsonResponse
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function update($subscriptionId)
    {
        $subscription = $this->getSubscription($subscriptionId);

        $subscription->swapAndInvoice($this->request->input('plan'));

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * @param $subscriptionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel($subscriptionId)
    {
        $subscription = $this->getSubscription($subscriptionId);

        if ($this->request->input('now')) {
            $subscription->cancelNow();
        } else {
            $subscription->cancel();
        }

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * @param $subscriptionId
     * @return \Illuminate\Http\JsonResponse
     */
    public function resume($subscriptionId)
    {
        $subscription = $this->getSubscription($subscriptionId);

        $subscription->resume();

        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * @param  \Laravel\Cashier\Subscription  $subscription
     * @return array
     * @throws \Stripe\Exception\ApiErrorException
     */
    protected function formatSubscription(Subscription $subscription)
    {
        $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_id);

        return array_merge($subscription->toArray(), [
            'plan_amount'           => $stripeSubscription->plan->amount,
            'plan_interval'         => $stripeSubscription->plan->interval,
            'plan_currency'         => $stripeSubscription->plan->currency,
            'plan'                  => $subscription->stripe_plan,
            'stripe_plan'           => $stripeSubscription->plan->nickname,
            'ended'                 => $subscription->ended(),
            'cancelled'             => $subscription->cancelled(),
            'active'                => $subscription->active(),
            'on_trial'              => $subscription->onTrial(),
            'on_grace_period'       => $subscription->onGracePeriod(),
            'charges_automatically' => $stripeSubscription->billing == 'charge_automatically',
            'created_at'            => $this->formatDate($stripeSubscription->billing_cycle_anchor),
            'ended_at'              => $this->formatDate($stripeSubscription->ended_at),
            'current_period_start'  => $this->formatDate($stripeSubscription->current_period_start),
            'current_period_end'    => $this->formatDate($stripeSubscription->current_period_end),
            'days_until_due'        => $stripeSubscription->days_until_due,
            'cancel_at_period_end'  => $stripeSubscription->cancel_at_period_end,
            'canceled_at'           => $stripeSubscription->canceled_at,
        ]);
    }

    /**
     * Format the plans collection.
     *
     * @param  \Stripe\Collection  $plans
     * @return array
     */
    protected function formatPlans($plans)
    {
        return collect($plans->data)->where('active', true)->map(function (Plan $plan) {
            return [
                'id'             => $plan->id,
                'name'           => $plan->nickname,
                'price'          => $plan->amount,
                'interval'       => $plan->interval,
                'currency'       => $plan->currency,
                'interval_count' => $plan->interval_count,
            ];
        })->toArray();
    }

    /**
     * @param $invoices
     * @return array
     */
    protected function formatInvoices($invoices)
    {
        return collect($invoices)->map(function ($invoice) {
            return [
                'id'           => $invoice->id,
                'total'        => $invoice->total,
                'attempted'    => $invoice->attempted,
                'charge_id'    => $invoice->charge,
                'currency'     => $invoice->currency,
                'period_start' => $this->formatDate($invoice->period_start),
                'period_end'   => $this->formatDate($invoice->period_end),
                'link'         => $invoice->hosted_invoice_url,
                'subscription' => $invoice->subscription,
            ];
        })->toArray();
    }

    /**
     * @param $methods
     * @return array
     */
    protected function formatPaymentMethods($methods)
    {
        $data = collect($methods)->map(function ($method) {
            if(is_null($method->card))
                return [];

            return [
                'id'         => $method->card->id,
                'brand'      => $method->card->brand,
                'country'    => $method->card->country,
                'last_4'     => $method->card->last4,
                'expiration' => $method->card->exp_month.'/'.$method->card->exp_year,
                'link'       => config('uleague.urls.payment-method') . '/remove/',
            ];
        })->toArray();

        return array_filter($data);
    }

    /**
     * @param  mixed  $value
     * @return string|null
     */
    protected function formatDate($value)
    {
        return $value ? Carbon::createFromTimestamp($value)->toDateTimeString() : null;
    }

    /**
     * @param $subscriptionId
     * @return mixed
     */
    private function getSubscription($subscriptionId)
    {
        $stripeModel = env('CASHIER_SUBSCRIPTION_MODEL', Laravel\Cashier\Subscription::class);

        /** @var \Illuminate\Database\Eloquent\Model $subscriptionModel */
        $subscriptionModel = (new $stripeModel());
        /** @var \Laravel\Cashier\Subscription|\Illuminate\Database\Eloquent\Model $subscription */
        $subscription = $subscriptionModel->find($subscriptionId);
        return $subscription;
    }
}
