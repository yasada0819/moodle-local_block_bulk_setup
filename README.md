# コースブロック一括設置 / Bulk course block placement

Moodle 4.5 以上の既存コースへ、管理者が選択した複数ブロックを一括追加するローカルプラグインです。

## できること

1. 複数カテゴリを選び、子カテゴリを含めて候補コースを抽出します。
2. 候補すべて・指定コースのみ・指定コースを除外、のいずれかで対象を確定します。
3. 有効なインストール済みブロックを複数選択します。
4. コースとブロックの組み合わせごとに、重複・ブロックの適用可否・テーマ領域を確認します。
5. 追加、スキップ、失敗見込みを確認してから、バックグラウンドタスクとして実行します。

Boost のブロックドロワに対応する `side-pre` を配置先にしています。Boost Union もこの領域を提供する構成なら同様に利用できます。対象コースのテーマに領域がない場合、実行前プレビューと実行結果でスキップとして記録します。

## Install

Place this directory at `local/block_bulk_setup` in the Moodle source tree, then visit **Site administration > Notifications** to complete the installation.

Grant the system capability `local/block_bulk_setup:manage` only to roles allowed to make bulk course changes. The capability is intentionally treated as a configuration and data-loss risk.

Open **Site administration > Courses > Bulk course block placement**.

## Operational notes

- The plugin uses the default configuration of each block. It does not try to fill arbitrary block-specific configuration forms.
- A block that does not allow multiple instances is always skipped when it already exists in the course.
- For blocks that allow multiple instances, the final wizard step applies the selected policy: add another instance or skip it.
- Every queued run records its initiator, exact target course IDs, selected blocks, status, and per-course result. Selection is fixed at queue time; a category changed later does not alter the run.
- Moodle cron must be operating for queued runs to start. Use **Site administration > Server > Tasks > Ad hoc tasks** for operational diagnostics.

## Scope and test matrix

The initial target is Moodle 4.5+ with core Boost and Boost Union. Before production use, test on a staging copy with the actual installed block types and all course themes used by the selected categories.

## License

GNU GPL v3 or later.
