<?php
/*
Plugin Name: GitHub Reop List
Plugin URI: http://fatfolderdesign.com/
Description: Get a list of all your GitHub repositories in a post-friendly format with a simple and flexible shortcode. Shortcode deatils located in the "Help" menu on post pages.
Version: 1.2.2
Author: Phillip Gooch
Author URI: mailto:phillip.gooch@gmail.com
License:  http://www.gnu.org/licenses/gpl-2.0.html
*/

class github_repo_list {

	public function __construct(){
		// Add the shortcode
		add_shortcode('github-repo-list',array($this,'github_repo_list_shortcode'));
		// Add the help tab addition
		add_action('load-post.php'		,array($this,'github_repo_list_help_tab'));
		add_action('load-post-new.php'	,array($this,'github_repo_list_help_tab'));
	}

	public function github_repo_list_shortcode($attributes){

		// Default settings
		$attributes = array_merge(array(
			'filtered_repos'	=> '',
			'link_target'		=> '_blank',
			'order'				=> 'name',
			'strip_name_dashes'	=> 'true',
			'title_wrapper'		=> 'h2',
			'username'			=> '',
			// This is the only github side thing that we have a default for
			'fork'				=> 'false',
		),(array)$attributes);

		// Where going to take the attributes that area shortcode-specific and remove them from the list, turn them into normal varibles
		foreach(array(
			'username',
			'title_wrapper',
			'order',
			'strip_name_dashes',
			'filtered_repos',
			'link_target',
		) as $n => $attribute){
			${$attribute} = $attributes[$attribute];
			unset($attributes[$attribute]);
		}
		// $filtered_repos needs a touch more work
		$filtered_repos = explode(',',$filtered_repos);

		// Check if the fork is blank, that means it should be removed
		if($attributes['fork']==''){
			unset($attributes['fork']);
		}

		// First we need to check for a username, if we don't have one were already finished with this.
		if($username==''){
			return '<b>The github-repo-list shortcode requires a "username" attribute.</b>';
		}

		// Now lets load the users repository list
		$c = curl_init('https://api.github.com/users/'.$username.'/repos');
		curl_setopt_array($c,array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT      => 'github-repo-list wordpress plugin',
			CURLOPT_SSL_VERIFYPEER => false,

		));
		$repos = curl_exec($c);
		curl_close($c);
		$repos = json_decode($repos,true);

		// If we didn't get something back we expected lets toss out an error
		if(!is_array($repos)){
			return '<b>The github-repo-list shortcode encountered an error and was unable to get a list of repositories for "'.$username.'".</b>';
		}

		// lets confirm we actually got a list of repositories
		if(isset($repos['message'])){
			if($repos['message']=='Not Found'){
				return '<b>The github-repo-list shortcode was unable to find a GitHub user with the name "'.$username.'".</b>';
			}else{
				// There liken was another error, since the message only shows when it needs to
				return '<b>The github-repo-list shortcode recived an unexpected message: '.$repos['message'].'.</b>';
			}
		}

		// Lets filter and sort that list of repositories.
		$sort_array = array();
		foreach($repos as $id => $fields){

			// Are going going to show this repository? (this way because there may not be any attributes left)
			$show = true;

			// Check if the name is on the filtered list.
			if(in_array($fields['name'],$filtered_repos)){
				$show=false;
			}else{

				// Check the remaining attributes options against the repository
				foreach($attributes as $k => $v){
					// Convert bools to strings, everything else should be good
					if(is_bool($fields[$k])){
						$fields[$k] = ($fields[$k]?'true':'false');
					}
					if($fields[$k]!==$v){
						$show = false;
					}
				}

			}

			// Toss is in the sort array
			if($show){
				$sort_array[strtolower($fields[$order])] = $id;
			}
		}
		// And sort
		ksort($sort_array);

		// Create a varible for the output to go to.
		$output = '';

		// Loop through the sort array, outputting html as we go
		foreach($sort_array as $name => $id){
			// Before we do the actual output if you want to modify the name any lets make that happen
			$name = $repos[$id]['name'];
			if($strip_name_dashes=='true'){
				$name = str_ireplace('-',' ',$name);
			}
			// Now we can do the output
			$output .= '
			<'.$title_wrapper.' class="github-repo-list">
				<a href="'.$repos[$id]['html_url'].'" target="'.$link_target.'">'.$name.'</a>
			</'.$title_wrapper.'>
			<p class="github-repo-list">'.$repos[$id]['description'].'</p>';
		}

		/* // Semi-usefull debug info
		$output .= 'Start github-repo-list debug...';
		$output .= 'Attributes <pre>'.print_r($attributes,true).'</pre>';
		$output .= 'Sort (by "'.$sort.'")<pre>'.print_r($sort_array,true).'</pre>';
		$output .= 'All Repo Data <pre>'.print_r($repos,true).'</pre>';
		$output .= '... end github-repo-list debug';
		*/

		// Finally return it, were done.
		return $output;
	}

	public function github_repo_list_help_tab(){
		// Get the screen and add the help in
		$screen = get_current_screen();
		$screen->add_help_tab( array(
			'id'      => 'shortcode_overview',
			'title'   => __( 'GitHub Repo List Shortcode','github-repo-list' ),
			'content' => '
				<p>The <code>[github-repo-list]</code> shortcode pulls a list of your repositories from GitHub and displays them in a page friendly manner. The shortcode supports the following attributes.</p>
				<table>
					<tr>
						<th>Attribute</th>
						<th>Default</th>
						<th>Details</th>
					</tr>
					<tr>
						<td valign="top">username</td>
						<td valign="top"><span style="opacity:.5">None</span></td>
						<td valign="top"><b>Required.</b> Your github username, used to pull your repositories.</td>
					</tr>
					<tr>
						<td valign="top">order</td>
						<td valign="top">name</td>
						<td valign="top">The repository detail to sort by (more details on repository detail below).</td>
					</tr>
					<tr>
						<td valign="top">title_wrapper</td>
						<td valign="top">h2</td>
						<td valign="top">The tag that you want to wrap the repository titles with, just the tag, the rest of the formatting and classes are automatically added.</td>
					</tr>
					<tr>
						<td valign="top">link_target</td>
						<td valign="top">_blank</td>
						<td valign="top">The target that will be given to the repository links.</td>
					</tr>
					<tr>
						<td nowrap valign="top">strip_name_dashes &nbsp;</td>
						<td valign="top">true</td>
						<td valign="top">Wether or not to string the dashes from repository names, can be either true or anything else for false.</td>
					</tr>
					<tr>
						<td valign="top">filtered_repos</td>
						<td valign="top"><span style="opacity:.5">(blank)</span></td>
						<td valign="top">A comma seperated list of repositories to be filtered out regardless of other settings, use the official GitHub name.</td>
					</tr>
				</table>
				<p>In addition to these settings you can filter against any repository detail except owner. A full list of details can be viewed by accessing the the API feed from <a href="https://api.github.com/users/pgooch/repos" target="_blank">https://api.github.com/users/{{your_username_here}}/repos</a>. The only default repository attribute filter is <code>fork="false"</code>, this can be overridden by passing a blank to the fork attribute.<p>
				<p>An example of a propert shortcode might look something like this: <code>[github-repo-list username="pgooch" title_wrapper="h4" ]</code>, which is very similar to the one I use on my site and pulls a list of all my repositories (except forks of other peoples) and wraps the title in an h4 tag instead of the default h2 tag.</p>
				',
			)
		);

	}

}
$github_repo_list = new github_repo_list();


/*
add_action( 'load-post.php', 'add_shortcode_help_tab' );
add_action( 'load-post-new.php', 'add_shortcode_help_tab' );

function add_shortcode_help_tab() {
	$screen = get_current_screen();
	if ( $screen->id == 'post' ) {
		// documentation for shortcode
		$content = '<p>';
		$content .= __( "this is documentation text for my shortcode", 'text-domain' );
		$content .= '</p>';

		$screen->add_help_tab( array(
			'id'      => 'shortcode_overview',
			'title'   => __( 'My Shortcode' ),
			'content' => $content,
			)
		);
	}
}
*/