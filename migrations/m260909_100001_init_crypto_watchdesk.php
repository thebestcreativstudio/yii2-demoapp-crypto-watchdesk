<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * All tables for the demo. Read once — names match models 1:1.
 */
final class m260909_100001_init_crypto_watchdesk extends Migration
{
    public function safeUp(): void
    {
        // Demo login (password hashed in seed migration step below)
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string(64)->notNull()->unique(),
            'password_hash' => $this->string(255)->notNull(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        // Coins the user follows
        $this->createTable('{{%watchlist_item}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'coingecko_id' => $this->string(64)->notNull(), // e.g. bitcoin
            'symbol' => $this->string(32)->notNull()->defaultValue(''),
            'name' => $this->string(128)->notNull()->defaultValue(''),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex('idx_watch_user_coin', '{{%watchlist_item}}', ['user_id', 'coingecko_id'], true);
        $this->addForeignKey('fk_watch_user', '{{%watchlist_item}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');

        // History of prices (worker writes rows here)
        $this->createTable('{{%price_snapshot}}', [
            'id' => $this->primaryKey(),
            'coingecko_id' => $this->string(64)->notNull(),
            'captured_at' => $this->dateTime()->notNull(),
            'price_usd' => $this->decimal(24, 8)->notNull(),
            'volume_24h' => $this->decimal(24, 2)->notNull()->defaultValue(0),
            'market_cap' => $this->decimal(24, 2)->notNull()->defaultValue(0),
        ]);
        $this->createIndex('idx_snap_coin_time', '{{%price_snapshot}}', ['coingecko_id', 'captured_at']);

        // Paper portfolio holdings
        $this->createTable('{{%portfolio_holding}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'coingecko_id' => $this->string(64)->notNull(),
            'quantity' => $this->decimal(24, 8)->notNull(),
            'cost_basis_usd' => $this->decimal(24, 8)->null(), // optional buy price per coin
            'updated_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex('idx_port_user_coin', '{{%portfolio_holding}}', ['user_id', 'coingecko_id'], true);
        $this->addForeignKey('fk_port_user', '{{%portfolio_holding}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');

        // Alert rules: e.g. abs(price change %) >= threshold
        $this->createTable('{{%alert_rule}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'coingecko_id' => $this->string(64)->notNull(),
            'threshold_percent' => $this->decimal(8, 2)->notNull(), // e.g. 5.00
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->addForeignKey('fk_alert_user', '{{%alert_rule}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');

        // Fired alerts (in-app inbox)
        $this->createTable('{{%notification}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'message' => $this->string(512)->notNull(),
            'coingecko_id' => $this->string(64)->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'is_read' => $this->boolean()->notNull()->defaultValue(false),
        ]);
        $this->addForeignKey('fk_notif_user', '{{%notification}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');

        // Demo user: demo / demo1234
        $hash = password_hash('demo1234', PASSWORD_DEFAULT);
        $this->insert('{{%user}}', [
            'username' => 'demo',
            'password_hash' => $hash,
        ]);

        // Default watchlist for demo user
        $userId = (int)$this->db->getLastInsertID();
        foreach ([
            ['bitcoin', 'btc', 'Bitcoin'],
            ['ethereum', 'eth', 'Ethereum'],
            ['solana', 'sol', 'Solana'],
        ] as [$id, $sym, $name]) {
            $this->insert('{{%watchlist_item}}', [
                'user_id' => $userId,
                'coingecko_id' => $id,
                'symbol' => $sym,
                'name' => $name,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%notification}}');
        $this->dropTable('{{%alert_rule}}');
        $this->dropTable('{{%portfolio_holding}}');
        $this->dropTable('{{%price_snapshot}}');
        $this->dropTable('{{%watchlist_item}}');
        $this->dropTable('{{%user}}');
    }
}
