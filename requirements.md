You are tasked with creating a robust PHP application for a vending machine system. This system should support features like product management, inventory tracking, purchase transactions, and user authentication. Each product should have the following properties:
- ID (int)
- Name (string)
- Price (decimal)
- QuantityAvailable (int)

Products:
- Coke - 3.99 USD
- Pepsi - 6.885 USD
- Water - 0.5 USD

## Tasks
1. Describe how you would set up the database for this application using PHP and MySQL, including details about tables for products, users, and transactions, and defining necessary fields and relationships.
2. Implement database access using PHP’s PDO, creating a connection class to manage the database connection and perform CRUD operations for products, users, and transactions.
3. Implement user authentication using PHP sessions and password hashing, supporting different roles such as Admin and User.
4. Configure role-based access control by checking the user’s role stored in the session before allowing access to certain pages or functionalities.
5. Write a PHP controller named ProductsController to handle CRUD operations for products.
6. Implement a function within the ProductsController to manage the purchasing process, updating product quantity and logging transactions.
7. Create PHP views for each of the CRUD operations, ensuring only Admins can access the management views.
8. Develop a view for listing all products, including implementing pagination and sorting features.
9. Create the view for purchasing a product, displaying necessary product details and handling user input for the purchase process.
10. Set up routing in a PHP application, defining routes for the ProductsController, including CRUD operations and the purchase process.
11. Implement attribute routing for the purchase action, structuring routes for clarity and SEO-friendly URLs.12. Add validation to the product input form to ensure all fields are required, the price is positive, and available quantity is non-negative.
13. Implement both server-side and client-side validation, handling validation errors in the views.
14. Write unit tests for the ProductsController using PHPUnit, including tests for various scenarios and edge cases.
15. Use dependency injection and mocking in PHP to test controller actions independently of the database and other services.
16. Create a RESTful API in PHP to allow other frontend applications to interact with the vending machine system.
17. Implement token-based authentication (e.g., JWT) for secure access to the API, ensuring authorized actions.
