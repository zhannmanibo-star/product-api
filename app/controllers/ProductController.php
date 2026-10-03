<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $this->call->library('api');

        // Preflight requests do not require a login token.
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            $this->api->respond(null, 204);
        }

        $this->call->database();

        // Every product operation requires a valid token.
        $auth = $this->api->require_jwt();

        $user = $this->db->raw(
            'SELECT id FROM users WHERE id = ? AND is_active = 1',
            [$auth['sub']]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->api->respond_error('Unauthorized.', 401);
        }
    }

    public function index()
    {
        $products = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at
             FROM products
             ORDER BY id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->api->respond(['products' => $products]);
    }

    public function store()
    {
        $data = $this->validated_product();

        $this->db->raw(
            'INSERT INTO products
                (product_name, description, price, quantity)
             VALUES (?, ?, ?, ?)',
            [
                $data['product_name'],
                $data['description'],
                $data['price'],
                $data['quantity']
            ]
        );

        $id = $this->db->raw(
            'SELECT LAST_INSERT_ID()'
        )->fetchColumn();

        $product = $this->db->raw(
            'SELECT * FROM products WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        $this->api->respond([
            'message' => 'Product added successfully.',
            'product' => $product
        ], 201);
    }
    public function update($id)
{
    if (!ctype_digit((string) $id) || (int) $id < 1) {
        $this->api->respond_error('Invalid product ID.', 400);
    }

    $existing = $this->db->raw(
        'SELECT id FROM products WHERE id = ?',
        [$id]
    )->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        $this->api->respond_error('Product not found.', 404);
    }

    $data = $this->validated_product();

    $this->db->raw(
        'UPDATE products
         SET product_name = ?, description = ?, price = ?, quantity = ?
         WHERE id = ?',
        [
            $data['product_name'],
            $data['description'],
            $data['price'],
            $data['quantity'],
            $id
        ]
    );

    $product = $this->db->raw(
        'SELECT * FROM products WHERE id = ?',
        [$id]
    )->fetch(PDO::FETCH_ASSOC);

    $this->api->respond([
        'message' => 'Product updated successfully.',
        'product' => $product
    ]);
}

public function destroy($id)
{
    if (!ctype_digit((string) $id) || (int) $id < 1) {
        $this->api->respond_error('Invalid product ID.', 400);
    }

    $statement = $this->db->raw(
        'DELETE FROM products WHERE id = ?',
        [$id]
    );

    if ($statement->rowCount() === 0) {
        $this->api->respond_error('Product not found.', 404);
    }

    $this->api->respond([
        'message' => 'Product deleted successfully.'
    ]);
}

    private function validated_product()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            $this->api->respond_error('Send a valid JSON request.', 400);
        }

        $name = $input['product_name'] ?? '';
        $description = $input['description'] ?? '';
        $price = $input['price'] ?? '';
        $quantity = $input['quantity'] ?? null;

        if (
            !is_string($name) ||
            !preg_match('/^.{1,100}$/us', trim($name))
        ) {
            $this->api->respond_error(
                'Product name must contain 1–100 characters.',
                422
            );
        }

        if (!is_string($description) || strlen($description) > 65535) {
            $this->api->respond_error('Description is invalid or too long.', 422);
        }

        if (
            !(is_string($price) || is_int($price) || is_float($price)) ||
            !preg_match('/^\d{1,8}(\.\d{1,2})?$/', (string) $price)
        ) {
            $this->api->respond_error(
                'Price must be 0–99999999.99, with at most 2 decimal places.',
                422
            );
        }

        if (
            !(is_string($quantity) || is_int($quantity)) ||
            filter_var($quantity, FILTER_VALIDATE_INT, [
                'options' => [
                    'min_range' => 0,
                    'max_range' => 2147483647
                ]
            ]) === false
        ) {
            $this->api->respond_error(
                'Quantity must be a non-negative whole number.',
                422
            );
        }

        return [
            'product_name' => trim($name),
            'description' => trim($description),
            'price' => (string) $price,
            'quantity' => (int) $quantity
        ];
    }

    public function options()
    {
        $this->api->respond(null, 204);
    }
}