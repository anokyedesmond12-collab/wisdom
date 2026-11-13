ALTER TABLE schools
MODIFY COLUMN subscription_plan ENUM(
    'Basic',
    'Standard',
    'Premium',
    'Basic Monthly',
    'Basic Quarterly',
    'Basic 6 Months'
) NOT NULL;
