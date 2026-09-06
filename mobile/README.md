# IHLink Datasub Mobile

Target: one Flutter client for Android and iOS, backed by the IHLink authenticated REST API.

## Navigation
Home · Services · Wallet · History · Account

## Native capabilities
- secure token storage
- biometric unlock after first authentication
- push notification integration point
- contact picker for data/airtime recipients
- receipt sharing
- deep links into transactions
- cached recent transaction history

## Release architecture
Flutter Android/iOS -> HTTPS REST API -> IHLink PHP service -> MySQL -> payment/provider adapters.

The mobile app must never contain provider secrets, Paystack secret keys, database credentials, or administrative credentials. Those remain server-side.

## Build sequence
1. Stabilize `/api/v1` contract.
2. Implement auth/token lifecycle.
3. Implement services/catalogue.
4. Implement wallet and history.
5. Implement purchases with transaction PIN.
6. Add biometric unlock, notifications, contacts and sharing.
7. Run Android/iOS release signing and store review preparation.
