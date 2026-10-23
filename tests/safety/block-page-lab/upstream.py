import socket,struct,ipaddress
s=socket.socket(socket.AF_INET,socket.SOCK_DGRAM);s.bind(('127.0.0.1',15300))
while True:
 data,addr=s.recvfrom(4096);end=12
 while data[end]: end+=data[end]+1
 end+=1;kind=struct.unpack('!H',data[end:end+2])[0];q=data[12:end+4]
 payload=ipaddress.ip_address('2a02:c207:2368:3678::1' if kind==28 else '213.199.47.105').packed
 answer=b'\xc0\x0c'+struct.pack('!HHIH',kind,1,60,len(payload))+payload
 s.sendto(data[:2]+struct.pack('!HHHHH',0x8180,1,1,0,0)+q+answer,addr)
