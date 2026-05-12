<h1>Claim your Ysabelle Retail purchase</h1>

<p>Thanks for shopping with Ysabelle Retail in store.</p>
<p>Your paid walk-in purchase <strong>{{ $claim->order->order_number }}</strong> can be linked to your account so you can leave verified product reviews.</p>

<p><strong>Purchased items:</strong></p>
<ul>
    @foreach ($claim->order->items as $item)
        <li>{{ $item->product_name }} @if($item->variant_name)({{ $item->variant_name }})@endif</li>
    @endforeach
</ul>

<p>This secure link expires on {{ $claim->expires_at?->timezone(config('app.timezone'))->format('F j, Y g:i A') }}.</p>
<p><a href="{{ $claimUrl }}">Claim this purchase</a></p>
<p>If you already have a Ysabelle Retail account with this email address, sign in first. If not, create an account with the same email address before claiming.</p>
