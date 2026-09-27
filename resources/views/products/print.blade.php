<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Stock Report #{{ $reportCode }}</title>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td><strong>{{ $storeName }}</strong></td>
            <td align="right"><strong>Product Stock Report</strong> #{{ $reportCode }}</td>
        </tr>
        <tr>
            <td>{{ $storeAddress }}<br>{{ format_indian_phone($storePhone) }} | {{ $storeEmail }}</td>
            <td align="right">Generated at {{ $generatedAt->format('d M Y, h:i a') }}<br>Total products: {{ number_format($summary['totalProducts']) }}<br>Showing: {{ number_format($summary['displayedProducts']) }}</td>
        </tr>
    </table>

    <br>

    <table width="100%" border="1" cellpadding="4" cellspacing="0">
        <tr bgcolor="#1d4ed8">
            <th align="left">SKU</th>
            <th align="left">Product</th>
            <th align="left">Category</th>
            <th align="left">Unit</th>
            <th align="right">MRP</th>
            <th align="right">Min Qty</th>
            <th align="left">Status</th>
        </tr>
        @foreach($products as $product)
            <tr>
                <td>{{ $product->sku ?: '-' }}</td>
                <td>{{ $product->name ?: '-' }}</td>
                <td>{{ $product->category_name ?: '-' }}</td>
                <td>{{ $product->unit_symbol ?: '-' }}</td>
                <td align="right">{{ format_money($product->mrp) }}</td>
                <td align="right">{{ number_format((int) $product->min_stock) }}</td>
                <td>{{ $product->is_active ? 'Active' : 'Inactive' }}</td>
            </tr>
        @endforeach
    </table>

    <br>
    <div>CSV and XLSX exports include the full product list. This PDF shows a preview slice to keep rendering reliable.</div>
</body>
</html>
