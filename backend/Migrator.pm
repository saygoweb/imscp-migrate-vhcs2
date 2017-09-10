=head1 NAME

 Plugin::Migrator

=cut

# i-MSCP Migrator plugin
# Copyright (C) 2017 Cambell Prince <cambell.prince@gmail.com>
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

package Plugin::Migrator;

use strict;
use warnings;
use iMSCP::Database;
use iMSCP::Debug;
use iMSCP::Dir;
use iMSCP::EventManager;
use iMSCP::Execute;
use iMSCP::File;
use iMSCP::Rights;
use iMSCP::Service;
use iMSCP::TemplateParser;
use Servers::httpd;
use Socket;
use version;
use parent 'Common::SingletonClass';

use Data::Dumper;

=head1 DESCRIPTION

 This package provides the backend part for the i-MSCP Migrator plugin.

=head1 PUBLIC METHODS

=over 4

=item install()

 Perform install tasks

 Return int 0 on success, other on failure

=cut

sub install
{
    my $self = shift;

    my $rs = $self->_checkRequirements();
    return $rs if $rs;

    0;
}

=item uninstall()

 Perform uninstall tasks

 Return int 0 on success, other on failure

=cut

sub uninstall
{
    my $self = shift;

    # my $rs = $self->{'db'}->doQuery( 'd', "DELETE FROM domain_dns WHERE owned_by = 'Migrator_Plugin'" );
    # unless (ref $rs eq 'HASH') {
    #     error( $rs );
    #     return $rs;
    # }

    my $rs = 0;
    # my $rs = $self->_migratorConfig( 'deconfigure' );
    return $rs if $rs;

    # TODO Currently we stop short of removing existing certs and unintsalling certbotauto CP 2017-06

    # $rs = iMSCP::Dir->new( dirname => '/etc/migrator' )->remove();

    return $rs;
}

=item update($fromVersion)

 Perform update tasks

 Param string $fromVersion Version from which the plugin is being updated
 Return int 0 on success, other on failure

=cut

sub update
{
    my ($self, $fromVersion) = @_;
    my $rs = 0;

    return $rs if $rs;

    0;
}

=item change()

 Perform change tasks

 Return int 0 on success, other on failure

=cut

sub change
{
    0;
}

=item enable()

 Perform enable tasks

 Return int 0 on success, other on failure

=cut

sub enable
{
    my $self = shift;

    # my $rs = $self->{'db'}->doQuery(
    #     'u', 'UPDATE domain_dns SET domain_dns_status = ? WHERE owned_by = ?', 'toenable', 'Migrator_Plugin'
    # );
    # unless (ref $rs eq 'HASH') {
    #     error( $rs );
    #     return $rs;
    # }

    # $rs = setRights(
    #     '/etc/migrator',
    #     {
    #         user      => 'migrator',
    #         group     => 'migrator',
    #         dirmode   => '0750',
    #         filemode  => '0640',
    #         recursive => 1
    #     }
    # );
    # return $rs if $rs;

    0;
}

=item disable()

 Perform disable tasks

 Return int 0 on success, other on failure

=cut

sub disable
{
    my $self = shift;

    # my $rs = $self->{'db'}->doQuery(
    #     'u', 'UPDATE domain_dns SET domain_dns_status = ? WHERE owned_by = ?', 'todisable', 'Migrator_Plugin'
    # );
    # unless (ref $rs eq 'HASH') {
    #     error( $rs );
    #     return $rs;
    # }

    0;
}

=item run()

 Create new entry for the Migrator

 Return int 0 on success, other on failure

=cut

sub run
{
    my $self = shift;

    # Find the domains for which we need to generate certificates
    my $rows = $self->{'db'}->doQuery(
        'migrator_id',
        "
            SELECT migrator_id, domain_id, alias_id, subdomain_id, cert_name, http_forward, status
            FROM migrator_sgw WHERE status IN('toadd', 'tochange', 'todelete')
        "
    );
    unless (ref $rows eq 'HASH') {
        error( $rows );
        return 1;
    }

    my @sql;
    for(values %{$rows}) {
        my ($type, $id) = $self->_domainTypeAndId($_->{'domain_id'}, $_->{'alias_id'}, $_->{'subdomain_id'});
        if ($_->{'status'} =~ /^to(?:add|change)$/) {
            my $rs = $self->_addCertificate( $type, $id, $_->{'cert_name'} );
            $rs |= $self->_updateForward( $type, $id, $_->{'cert_name'}, $_->{'http_forward'} );
            @sql = (
                'UPDATE migrator_sgw SET status = ? WHERE migrator_id = ?',
                ($rs ? scalar getMessageByType( 'error' ) || 'Unknown error' : 'ok'), $_->{'migrator_id'}
            );
        } elsif ($_->{'status'} eq 'todelete') {
            my $rs = $self->_deleteCertificate( $type, $id, $_->{'cert_name'} );
            $rs |= $self->_updateForward( $type, $id, $_->{'cert_name'}, 0 );
            if ($rs) {
                @sql = (
                    'UPDATE migrator_sgw SET status = ? WHERE migrator_id = ?',
                    (scalar getMessageByType( 'error' ) || 'Unknown error'), $_->{'migrator_id'}
                );
            } else {
                @sql = ('DELETE FROM migrator_sgw WHERE migrator_id = ?', $_->{'migrator_id'});
            }
        }
        # Update the status of the last operation
        # Comment out the below for dev testing CP 2017-06
        my $qrs = $self->{'db'}->doQuery( 'dummy', @sql );
        unless (ref $qrs eq 'HASH') {
            error( $qrs );
            return 1;
        }
    }

    0;
}

=back

=head1 PRIVATE METHODS

=over 4

=item _init()

 Initialize plugin

 Return Plugin::Migrator or die on failure

=cut

sub _init
{
    my $self = shift;

    # testmode enables the mocked certbot-auto that creates self-signed certificates
    # enabled = 1, disabled = 0
    $self->{'testmode'} = 0;

    $self->{'db'} = iMSCP::Database->factory();
    $self->{'httpd'} = Servers::httpd->factory();

    # iMSCP::EventManager->getInstance()->register( 'afterHttpdBuildConf', sub { $self->_onAfterHttpdBuildConf( @_ ); } );

    $self;
}

=item _checkRequirements()

 Check for requirements

 Return int 0 if all requirements are met, other otherwise

=cut

sub _checkRequirements
{
    my $ret = 0;

    # wget

    # for(qw/ migrator migrator-tools /) {
    #     if (execute( "dpkg-query -W -f='\${Status}' $_ 2>/dev/null | grep -q '\\sinstalled\$'" )) {
    #         error( sprintf( 'The `%s` package is not installed on your system', $_ ) );
    #         $ret ||= 1;
    #     }
    # }

    $ret;
}

=back

=head1 AUTHORS

 Cambell Prince <cambell.prince@gmail.com>

=cut

1;
__END__
