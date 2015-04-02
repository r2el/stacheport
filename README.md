# stacheport
Stacheport is a small, simple to use report template engine with mustache-like syntax for PHP

# usage

Stacheport can handle collections of arrays, objects and closures while also doing sums of numerical fields in the background. For instance:

[begin view file]
```
{{#records}}
	<tr><td>{{id}}</td><td>{{name}}</td><td>{{ functions.format_phone('{{cell}}', '{{phone}}') }}</td><td>Text</td></tr>
{{/records}}
{{#records.totals}}
	<tr><td>Total Count</td><td>{{counter}}</td></tr>
{{/records.totals}}
```
[end view file]

```php
$functions= new stdClass();

$functions->format_phone= function($phone,$phone2)
{
	return format_phone($phone > ''?$phone:$phone2);
};

$template= View::template('report');

echo Stacheport::factory($template)
	->bind('records',$records)
	->bind('functions',$functions)
	->render();
```

It can also handle grouping by a particular field and repeating the template for each unique field it is grouping by:

[begin view file]
```
{{records[0].salesperson}}

{{#records}}
				<div class='{{ functions.even_odd_row({{counter}}) }}' {{date}} {{sales}}</div>
{{/records}}

{{#records.totals}}
				<div>{{date}} {{sales}}</div>
{{/records.totals}}
```
[end view file]

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
