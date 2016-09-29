# stacheport
Stacheport is a small, simple to use report template engine with mustache-like syntax for PHP

# usage

Stacheport can handle collections of arrays, objects and closures while also doing sums of numerical fields in the background. For instance:

```php
$functions= (object) ['format_name' => function($name)
  {
    return uc_words($name);
  }
];

$template= View::template('report');

echo Stacheport::factory($template)
	->bind('records',$records)
	->bind('functions',$functions)
	->render();
```

[begin report view file]
```
{{#records}}
	<tr><td>{{date}}</td><td>{{ functions.format_name('{{salesperson}}') }}</td><td>{{sale_amount}}</td></tr>
{{/records}}
{{#records.totals}}
	<tr><td>Total Sales</td><td>{{counter}}</td><td>{{sale_amount}}</td></tr>
{{/records.totals}}
```
[end view file]

It can also handle grouping by a particular field and repeating the template for each unique field it is grouping by:

```php
$functions=new stdClass();

$functions->even_odd = function($counter)
{
	return ($counter %2==0)?'even':'odd';
};

$template= View::template('report');

echo Stacheport::factory($template)
  ->bind('records',$records)
  ->bind('functions',$functions)
  ->group_by('records','salesperson')
  ->render();
```

This would produce a report for each salesperson totals with date totals.		

[begin report view file]
```
{{records[0].salesperson}}

{{#records}}
	<div class='{{ functions.even_odd_row({{counter}}) }}' {{date}} {{sales_amount}}</div>
{{/records}}

{{#records.totals}}
	<div>Total: {{sales_amount}}</div>
{{/records.totals}}
```
[end view file]
