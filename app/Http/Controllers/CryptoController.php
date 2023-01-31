<?php

namespace App\Http\Controllers;

use App\Actions\CryptoTransaction;
use App\Http\Interfaces\CryptoServiceInterface;
use App\Http\Requests\CryptoBuyRequest;
use App\Http\Requests\CryptoSellRequest;
use App\Models\Transaction;
use App\Repositories\CryptoRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class CryptoController extends Controller
{
    public function __construct(CryptoServiceInterface $cryptoService, CryptoRepository $cryptoRepository)
    {
        $this->cryptoService = $cryptoService;
        $this->cryptoRepository = $cryptoRepository;
    }

    public function index(): View
    {
        return view('crypto.list', [
            'cryptoList' => $this->cryptoRepository->getList(),
        ]);
    }

    public function show(
        string $symbol
    ): View
    {
        foreach (Auth::user()->accounts->toArray() as $account) {
            $accountNumbers [] = $account['number'];
        }
//        var_dump($accountNumbers);die;
        $transactions = Transaction::sortable(['created_at', 'desc'])
            ->whereIn('beneficiary_account_number', $accountNumbers)
            ->orWhereIn('account_number', $accountNumbers)
            ->where(function ($query) use ($symbol) {
                $query
                    ->where('currency_payer', $symbol)
                    ->orWhere('currency_beneficiary', $symbol);
            });

        $symbol = strtoupper($symbol);
        return view('crypto.single', [
            'accounts' => Auth::user()->accounts,
            'crypto' => $this->cryptoRepository->getSingle($symbol),
            'assetOwned' => Auth::user()->assets->where('symbol', $symbol)->first() ?? null,
            'transactions' => $transactions
                ->filter(request()->only('search', 'from', 'to'))
                ->paginate()
                ->withQueryString(),
            'credit' => $transactions->where('currency_beneficiary', $symbol)->sum('amount_payer'),
            'debit' => $transactions->where('currency_payer', $symbol)->sum('amount_beneficiary'),
        ]);
    }

    public function buy(
        CryptoBuyRequest $request
    ): RedirectResponse
    {
        (new CryptoTransaction())->execute(
            $request->payerAccountNumber,
            $request->symbol,
            $request->assetAmount,
            Cache::get('latestPrice') * -1
        );
        Cache::forget('latestPrice');

        return redirect()->back();
    }

    public function sell(
        CryptoSellRequest $request
    ): RedirectResponse
    {
        (new CryptoTransaction())->execute(
            $request->payerAccountNumber,
            $request->symbol,
            $request->assetAmount,
            Cache::get('latestPrice')
        );
        Cache::forget('latestPrice');

        return redirect()->back();
    }

    public function search(): RedirectResponse
    {
        return redirect()->route('crypto.show', ['symbol' => request('symbol')]);
    }
}
